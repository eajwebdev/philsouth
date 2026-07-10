<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Site;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PhysicalCountController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermissionTo('inventory.view'), 403);

        return Inertia::render('inventory/count', [
            'sites' => $request->user()->accessibleSites()->map->only('id', 'code', 'name'),
            'items' => $this->itemOptions(),
            'canAdjust' => $request->user()->hasPermissionTo('receiving.manage'),
        ]);
    }

    /**
     * Record a physical count: compute the variance against the live balance
     * and, if non-zero, post a matching `adjustment` movement.
     */
    public function store(Request $request, StockService $stock): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo('receiving.manage'), 403);

        $data = $request->validate([
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')],
            'item_variant_id' => ['required', 'integer', Rule::exists('item_variants', 'id')],
            'counted_qty' => ['required', 'numeric', 'min:0'],
        ]);

        $site = Site::findOrFail($data['site_id']);
        abort_unless($request->user()->canAccessSite($site), 403);

        $variant = ItemVariant::findOrFail($data['item_variant_id']);
        $system = $stock->balance($site, $variant);
        $variance = round((float) $data['counted_qty'] - $system, 2);

        if (abs($variance) < 0.001) {
            return back()->with('success', "No variance for {$variant->sku} — count matches the system.");
        }

        $stock->postMovement(
            $site,
            $variant,
            $variance > 0 ? 'in' : 'out',
            'adjustment',
            abs($variance),
            [
                'movement_date' => now()->toDateString(),
                'created_by' => $request->user()->id,
                'remarks' => 'Physical count adjustment (system '.$system.' → counted '.$data['counted_qty'].')',
            ],
        );

        $sign = $variance > 0 ? '+' : '';
        return back()->with('success', "Adjustment posted for {$variant->sku}: {$sign}{$variance}.");
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function itemOptions()
    {
        return Item::query()
            ->where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)->orderByDesc('is_default')->orderBy('sku')])
            ->orderBy('code')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'description' => $item->description,
                'uom' => $item->uom,
                'has_variants' => $item->has_variants,
                'variants' => $item->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'label' => $v->label,
                    'uom' => $v->uom ?: $item->uom,
                    'barcode' => $v->barcode,
                    'is_default' => $v->is_default,
                ])->values(),
            ]);
    }
}
