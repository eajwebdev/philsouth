<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MonthlySummaryController extends Controller
{
    /** IN sources → summary column. */
    private const IN_MAP = [
        'purchase' => 'purchases',
        'warehouse_in' => 'warehouse_in',
        'transfer_in' => 'transfer_in',
    ];

    /** OUT sources → summary column. */
    private const OUT_MAP = [
        'usage' => 'usage',
        'transfer_out' => 'transfer_out',
        'loss_damage' => 'loss_damage',
        'return_supplier' => 'return_supplier',
        'warehouse_out' => 'warehouse_out',
        'sale_other' => 'sale_other',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermissionTo('reports.view'), 403);

        $user = $request->user();
        $siteId = $request->integer('site_id') ?: null;
        $month = $request->string('month')->value() ?: now()->format('Y-m');

        $summary = null;
        if ($siteId) {
            $site = Site::findOrFail($siteId);
            abort_unless($user->canAccessSite($site), 403);
            $summary = $this->buildSummary($site, $month);
        }

        return Inertia::render('reports/monthly-summary', [
            'sites' => $user->accessibleSites()->map->only('id', 'code', 'name'),
            'filters' => ['site_id' => $siteId, 'month' => $month],
            'summary' => $summary,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSummary(Site $site, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $isClosed = $end->isPast();

        // Beginning balance per variant: net of everything before the month.
        $beginning = DB::table('stock_movements')
            ->where('site_id', $site->id)
            ->whereDate('movement_date', '<', $start->toDateString())
            ->selectRaw("item_variant_id, SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) AS bal")
            ->groupBy('item_variant_id')
            ->pluck('bal', 'item_variant_id');

        // In-range totals per variant + source.
        $inRange = DB::table('stock_movements')
            ->where('site_id', $site->id)
            ->whereBetween('movement_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('item_variant_id, direction, source, SUM(quantity) AS qty')
            ->groupBy('item_variant_id', 'direction', 'source')
            ->get();

        // Variants involved = anything with a beginning balance or a movement this month.
        $variantIds = $beginning->keys()
            ->merge($inRange->pluck('item_variant_id'))
            ->unique()
            ->values();

        $variants = DB::table('item_variants')
            ->join('items', 'items.id', '=', 'item_variants.item_id')
            ->whereIn('item_variants.id', $variantIds)
            ->select(
                'item_variants.id',
                'item_variants.sku',
                'item_variants.label',
                'item_variants.uom as variant_uom',
                'items.description',
                'items.code',
                'items.uom as item_uom',
            )
            ->orderBy('items.code')
            ->get()
            ->keyBy('id');

        // Live balances for the reconcile check (closed months must match ending).
        $live = DB::table('site_stock')
            ->where('site_id', $site->id)
            ->whereIn('item_variant_id', $variantIds)
            ->pluck('balance', 'item_variant_id');

        $rows = [];
        $reconciles = true;

        foreach ($variantIds as $vid) {
            $v = $variants[$vid] ?? null;
            if (! $v) {
                continue;
            }

            $cols = array_fill_keys([...array_values(self::IN_MAP), ...array_values(self::OUT_MAP)], 0.0);
            $adjustmentIn = 0.0;
            $adjustmentOut = 0.0;

            foreach ($inRange->where('item_variant_id', $vid) as $m) {
                $qty = (float) $m->qty;
                if ($m->direction === 'in') {
                    if ($m->source === 'adjustment') {
                        $adjustmentIn += $qty;
                    } elseif (isset(self::IN_MAP[$m->source])) {
                        $cols[self::IN_MAP[$m->source]] += $qty;
                    }
                } else {
                    if ($m->source === 'adjustment') {
                        $adjustmentOut += $qty;
                    } elseif (isset(self::OUT_MAP[$m->source])) {
                        $cols[self::OUT_MAP[$m->source]] += $qty;
                    }
                }
            }

            $begin = (float) ($beginning[$vid] ?? 0);
            $totalIn = array_sum([$cols['purchases'], $cols['warehouse_in'], $cols['transfer_in'], $adjustmentIn]);
            $totalOut = array_sum([
                $cols['usage'], $cols['transfer_out'], $cols['loss_damage'],
                $cols['return_supplier'], $cols['warehouse_out'], $cols['sale_other'], $adjustmentOut,
            ]);
            $ending = $begin + $totalIn - $totalOut;

            if ($isClosed && abs($ending - (float) ($live[$vid] ?? 0)) > 0.001) {
                $reconciles = false;
            }

            $rows[] = [
                'variant' => [
                    'id' => (int) $v->id,
                    'sku' => $v->sku,
                    'label' => $v->label,
                    'description' => $v->description,
                    'code' => $v->code,
                    'uom' => $v->variant_uom ?: $v->item_uom,
                ],
                'beginning' => round($begin, 2),
                ...array_map(fn ($n) => round($n, 2), $cols),
                'adjustment' => round($adjustmentIn - $adjustmentOut, 2),
                'total_in' => round($totalIn, 2),
                'total_out' => round($totalOut, 2),
                'ending' => round($ending, 2),
            ];
        }

        return [
            'site' => $site->only('id', 'code', 'name', 'address'),
            'month' => $month,
            'month_label' => $start->format('F Y'),
            'is_closed' => $isClosed,
            'reconciles' => $reconciles,
            'rows' => $rows,
        ];
    }
}
