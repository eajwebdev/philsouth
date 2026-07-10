<?php

namespace App\Http\Controllers;

use App\Models\DeliveryReceipt;
use App\Models\Item;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\StockMovement;
use App\Models\TransferSlip;
use App\Models\User;
use App\Models\WithdrawalSlip;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $role = match (true) {
            $user->hasRole('superadmin') => 'superadmin',
            $user->hasRole('administrator') => 'administrator',
            $user->hasRole('engineer') => 'engineer',
            $user->hasRole('ics') => 'ics',
            default => 'ics',
        };

        $data = match ($role) {
            'superadmin', 'administrator' => $this->adminData(),
            'engineer' => $this->engineerData($user),
            default => $this->icsData($user),
        };

        return Inertia::render('dashboard', [
            'role' => $role,
            'data' => $data,
        ]);
    }

    /** Company-wide view. */
    protected function adminData(): array
    {
        $siteIds = Site::pluck('id');

        return [
            'kpis' => [
                'sites' => Site::count(),
                'users' => User::count(),
                'items' => Item::count(),
                'pending_approvals' => WithdrawalSlip::where('status', 'pending_approval')->count(),
            ],
            'stock_by_site' => Site::query()
                ->leftJoin('site_stock', 'site_stock.site_id', '=', 'sites.id')
                ->groupBy('sites.id', 'sites.code')
                ->select('sites.code as label', DB::raw('COALESCE(SUM(site_stock.balance), 0) as value'))
                ->orderByDesc('value')
                ->limit(8)
                ->get(),
            'movement_trend' => $this->movementTrend($siteIds),
            'top_issued' => $this->topIssued($siteIds),
            'setup_gaps' => $this->setupGaps(),
            'low_stock_count' => SiteStock::whereColumn('balance', '<=', 'min_qty')->count(),
        ];
    }

    /** Engineer: own sites, approvals-focused. */
    protected function engineerData(User $user): array
    {
        $siteIds = $user->siteIds();

        $pending = WithdrawalSlip::query()
            ->whereIn('site_id', $siteIds)
            ->where('status', 'pending_approval')
            ->with(['site:id,code,name', 'preparedBy:id,name'])
            ->withCount('items')
            ->latest()
            ->limit(10)
            ->get();

        return [
            'kpis' => [
                'awaiting_approval' => WithdrawalSlip::whereIn('site_id', $siteIds)->where('status', 'pending_approval')->count(),
                'my_sites' => $siteIds->count(),
                'low_stock' => SiteStock::whereIn('site_id', $siteIds)->whereColumn('balance', '<=', 'min_qty')->count(),
                'in_transit' => TransferSlip::where('status', 'in_transit')
                    ->where(fn ($q) => $q->whereIn('from_site_id', $siteIds)->orWhereIn('to_site_id', $siteIds))
                    ->count(),
            ],
            'pending_queue' => $pending->map(fn ($ws) => [
                'id' => $ws->id,
                'ws_no' => $ws->ws_no,
                'site' => $ws->site->code,
                'prepared_by' => $ws->preparedBy?->name,
                'items_count' => $ws->items_count,
                'date' => $ws->date->toDateString(),
            ]),
            'movement_trend' => $this->movementTrend($siteIds),
            'low_stock_items' => $this->lowStockItems($siteIds),
        ];
    }

    /** ICS: own sites, operational. */
    protected function icsData(User $user): array
    {
        $siteIds = $user->siteIds();
        $today = now()->toDateString();

        return [
            'kpis' => [
                'today_receipts' => DeliveryReceipt::whereIn('site_id', $siteIds)->where('status', 'posted')->whereDate('updated_at', $today)->count(),
                'today_released' => WithdrawalSlip::whereIn('site_id', $siteIds)->where('status', 'released')->whereDate('released_at', $today)->count(),
                'below_min' => SiteStock::whereIn('site_id', $siteIds)->whereColumn('balance', '<=', 'min_qty')->count(),
                'to_receive' => TransferSlip::whereIn('to_site_id', $siteIds)->where('status', 'in_transit')->count(),
            ],
            'week_flow' => $this->weekFlow($siteIds),
            'to_receive' => TransferSlip::query()
                ->whereIn('to_site_id', $siteIds)
                ->where('status', 'in_transit')
                ->with(['fromSite:id,code', 'toSite:id,code'])
                ->withCount('items')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn ($ts) => [
                    'id' => $ts->id,
                    'ts_no' => $ts->ts_no,
                    'from' => $ts->fromSite->code,
                    'to' => $ts->toSite->code,
                    'items_count' => $ts->items_count,
                ]),
            'my_slips' => WithdrawalSlip::query()
                ->whereIn('site_id', $siteIds)
                ->whereIn('status', ['draft', 'pending_approval', 'approved'])
                ->with('site:id,code')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn ($ws) => [
                    'id' => $ws->id,
                    'ws_no' => $ws->ws_no,
                    'site' => $ws->site->code,
                    'status' => $ws->status,
                ]),
            'low_stock_items' => $this->lowStockItems($siteIds),
        ];
    }

    /** Last 6 months of IN vs OUT totals (grouped in PHP for DB portability). */
    protected function movementTrend(Collection $siteIds): array
    {
        $start = now()->subMonths(5)->startOfMonth();

        $movements = StockMovement::query()
            ->when($siteIds->isNotEmpty(), fn ($q) => $q->whereIn('site_id', $siteIds))
            ->whereDate('movement_date', '>=', $start->toDateString())
            ->get(['movement_date', 'direction', 'quantity']);

        return collect(range(0, 5))->map(function ($i) use ($start, $movements) {
            $month = (clone $start)->addMonths($i);
            $inMonth = $movements->filter(fn ($m) => $m->movement_date->isSameMonth($month));

            return [
                'month' => $month->format('M'),
                'in' => (float) $inMonth->where('direction', 'in')->sum('quantity'),
                'out' => (float) $inMonth->where('direction', 'out')->sum('quantity'),
            ];
        })->all();
    }

    /** This week's IN/OUT per day (grouped in PHP for DB portability). */
    protected function weekFlow(Collection $siteIds): array
    {
        $start = now()->startOfWeek();

        $movements = StockMovement::query()
            ->when($siteIds->isNotEmpty(), fn ($q) => $q->whereIn('site_id', $siteIds))
            ->whereDate('movement_date', '>=', $start->toDateString())
            ->get(['movement_date', 'direction', 'quantity']);

        return collect(range(0, 6))->map(function ($i) use ($start, $movements) {
            $day = (clone $start)->addDays($i);
            $onDay = $movements->filter(fn ($m) => $m->movement_date->isSameDay($day));

            return [
                'day' => $day->format('D'),
                'in' => (float) $onDay->where('direction', 'in')->sum('quantity'),
                'out' => (float) $onDay->where('direction', 'out')->sum('quantity'),
            ];
        })->all();
    }

    /** Top 10 most-issued (usage OUT) items. */
    protected function topIssued(Collection $siteIds): array
    {
        return StockMovement::query()
            ->join('item_variants', 'item_variants.id', '=', 'stock_movements.item_variant_id')
            ->join('items', 'items.id', '=', 'item_variants.item_id')
            ->where('stock_movements.direction', 'out')
            ->where('stock_movements.source', 'usage')
            ->when($siteIds->isNotEmpty(), fn ($q) => $q->whereIn('stock_movements.site_id', $siteIds))
            ->groupBy('items.id', 'items.description')
            ->select('items.description as label', DB::raw('SUM(stock_movements.quantity) as value'))
            ->orderByDesc('value')
            ->limit(10)
            ->get()
            ->all();
    }

    protected function lowStockItems(Collection $siteIds): array
    {
        return SiteStock::query()
            ->whereIn('site_id', $siteIds)
            ->whereColumn('balance', '<=', 'min_qty')
            ->with(['variant:id,item_id,sku,label', 'variant.item:id,description', 'site:id,code'])
            ->orderBy('balance')
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'item' => $s->variant->item->description . ($s->variant->label ? ' — ' . $s->variant->label : ''),
                'sku' => $s->variant->sku,
                'site' => $s->site->code,
                'balance' => (float) $s->balance,
                'min' => (float) $s->min_qty,
            ])
            ->all();
    }

    /** Sites missing an engineer or an ICS. */
    protected function setupGaps(): array
    {
        return Site::query()
            ->with(['users:id'])
            ->withCount([
                'users as engineers_count' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'engineer')),
                'users as ics_count' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'ics')),
            ])
            ->get()
            ->filter(fn ($s) => $s->engineers_count === 0 || $s->ics_count === 0)
            ->take(8)
            ->map(fn ($s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'needs_engineer' => $s->engineers_count === 0,
                'needs_ics' => $s->ics_count === 0,
            ])
            ->values()
            ->all();
    }
}
