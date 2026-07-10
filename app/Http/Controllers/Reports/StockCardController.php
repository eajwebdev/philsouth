<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockCardController extends Controller
{
    private const IN_LABELS = [
        'purchase' => 'Supplier',
        'warehouse_in' => 'Other Project',
        'transfer_in' => 'Transfer In',
        'adjustment' => 'Adjustment',
    ];

    private const OUT_LABELS = [
        'usage' => 'Usage',
        'transfer_out' => 'Transfer Out',
        'loss_damage' => 'Loss & Damage',
        'return_supplier' => 'Return to Supplier',
        'warehouse_out' => 'Warehouse/EMD',
        'sale_other' => 'Sales / Others',
        'adjustment' => 'Adjustment',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermissionTo('reports.view'), 403);

        $user = $request->user();
        $siteId = $request->integer('site_id') ?: null;
        $variantId = $request->integer('item_variant_id') ?: null;

        $card = null;
        if ($siteId && $variantId) {
            $site = Site::findOrFail($siteId);
            abort_unless($user->canAccessSite($site), 403);
            $card = $this->buildCard($site, ItemVariant::with('item')->findOrFail($variantId));
        }

        return Inertia::render('reports/stock-card', [
            'sites' => $user->accessibleSites()->map->only('id', 'code', 'name'),
            'items' => $this->itemOptions(),
            'filters' => ['site_id' => $siteId, 'item_variant_id' => $variantId],
            'card' => $card,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCard(Site $site, ItemVariant $variant): array
    {
        $header = SiteStock::where('site_id', $site->id)
            ->where('item_variant_id', $variant->id)
            ->first();

        $movements = StockMovement::where('site_id', $site->id)
            ->where('item_variant_id', $variant->id)
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        $rows = $movements->map(function (StockMovement $m) {
            $isIn = $m->direction === 'in';

            return [
                'date' => $m->movement_date->toDateString(),
                'dr_ws_no' => $m->dr_ws_no,
                'incoming' => $isIn ? (self::IN_LABELS[$m->source] ?? $m->source) : null,
                'source_label' => $isIn ? (self::IN_LABELS[$m->source] ?? $m->source) : (self::OUT_LABELS[$m->source] ?? $m->source),
                'issued_to' => $m->issued_to,
                'in' => $isIn ? (float) $m->quantity : null,
                'out' => $isIn ? null : (float) $m->quantity,
                'balance' => (float) $m->balance_after,
                'remarks' => $m->remarks,
            ];
        });

        return [
            'site' => $site->only('id', 'code', 'name', 'address'),
            'variant' => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'label' => $variant->label,
                'uom' => $variant->effectiveUom(),
                'item' => $variant->item->only('id', 'code', 'description'),
            ],
            'header' => [
                'location' => $header?->location,
                'min_qty' => (float) ($header?->min_qty ?? 0),
                'max_qty' => $header?->max_qty !== null ? (float) $header->max_qty : null,
                'balance' => (float) ($header?->balance ?? 0),
            ],
            'rows' => $rows,
            'totals' => [
                'in' => $rows->sum(fn ($r) => $r['in'] ?? 0),
                'out' => $rows->sum(fn ($r) => $r['out'] ?? 0),
            ],
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function itemOptions()
    {
        return Item::query()
            ->with(['variants' => fn ($q) => $q->orderByDesc('is_default')->orderBy('sku')])
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
