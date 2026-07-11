<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermissionTo('inventory.view'), 403);

        $user = $request->user();

        $siteId = $request->integer('site_id') ?: null;
        if ($siteId) {
            $site = Site::findOrFail($siteId);
            abort_unless($user->canAccessSite($site), 403);
        }

        $base = SiteStock::query()
            ->forUser($user)
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($request->boolean('low_only'), fn ($q) => $q->whereColumn('balance', '<=', 'min_qty'))
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $s = $request->string('search')->value();
                $q->whereHas('variant', fn ($v) => $v
                    ->where('sku', 'like', "%{$s}%")
                    ->orWhere('barcode', 'like', "%{$s}%")
                    ->orWhereHas('item', fn ($i) => $i
                        ->where('code', 'like', "%{$s}%")
                        ->orWhere('description', 'like', "%{$s}%")));
            });

        // Total value across the whole filtered set (not just the current page).
        $totalValue = (float) (clone $base)->sum(DB::raw('balance * avg_cost'));

        $stock = $base
            ->with(['variant:id,item_id,sku,label,uom', 'variant.item:id,code,description,uom,category', 'site:id,code,name'])
            ->orderBy('site_id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('inventory/index', [
            'stock' => $stock,
            'sites' => $user->accessibleSites()->map->only('id', 'code', 'name'),
            'totalValue' => round($totalValue, 2),
            'canManage' => $user->hasPermissionTo('receiving.manage'),
            'filters' => [
                'search' => $request->string('search')->value(),
                'site_id' => $siteId,
                'low_only' => $request->boolean('low_only'),
            ],
        ]);
    }

    /**
     * Reorder report: every stock row at or below its minimum, with a suggested
     * order quantity (bring it up to max, or to min when no max is set).
     */
    public function reorder(Request $request): Response
    {
        abort_unless($request->user()->hasPermissionTo('inventory.view'), 403);

        $user = $request->user();
        $siteId = $request->integer('site_id') ?: null;
        if ($siteId) {
            abort_unless($user->canAccessSite(Site::findOrFail($siteId)), 403);
        }

        $rows = SiteStock::query()
            ->forUser($user)
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->whereColumn('balance', '<=', 'min_qty')
            ->where('min_qty', '>', 0)
            ->with(['variant:id,item_id,sku,label,uom', 'variant.item:id,code,description,uom', 'site:id,code,name'])
            ->orderByRaw('(min_qty - balance) DESC')
            ->paginate(10)
            ->withQueryString()
            ->through(function (SiteStock $s) {
                $target = $s->max_qty !== null ? (float) $s->max_qty : (float) $s->min_qty;
                return [
                    'id' => $s->id,
                    'location' => $s->location,
                    'min_qty' => (float) $s->min_qty,
                    'max_qty' => $s->max_qty !== null ? (float) $s->max_qty : null,
                    'balance' => (float) $s->balance,
                    'suggested' => max(0, round($target - (float) $s->balance, 2)),
                    'uom' => $s->variant->uom ?: $s->variant->item->uom,
                    'sku' => $s->variant->sku,
                    'item' => $s->variant->item->description.($s->variant->label ? ' — '.$s->variant->label : ''),
                    'site' => $s->site->code,
                ];
            });

        return Inertia::render('inventory/reorder', [
            'rows' => $rows,
            'sites' => $user->accessibleSites()->map->only('id', 'code', 'name'),
            'canManage' => $user->hasPermissionTo('receiving.manage'),
            'filters' => ['site_id' => $siteId],
        ]);
    }

    /**
     * Update reorder thresholds + location for a stock row. Scoped to a site the
     * user operates. Never touches balance (that stays ledger-driven).
     */
    public function updateThresholds(Request $request, SiteStock $stock): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo('receiving.manage'), 403);
        abort_unless($request->user()->canAccessSite($stock->site), 403);

        $data = $request->validate([
            'min_qty' => ['required', 'numeric', 'min:0'],
            'max_qty' => ['nullable', 'numeric', 'gte:min_qty'],
            'location' => ['nullable', 'string', 'max:100'],
        ]);

        $stock->update([
            'min_qty' => $data['min_qty'],
            'max_qty' => $data['max_qty'] ?? null,
            'location' => $data['location'] ?? null,
        ]);

        return back()->with('success', 'Reorder levels updated.');
    }
}
