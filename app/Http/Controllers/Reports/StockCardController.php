<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        [$from, $to] = $this->parseRange($request);

        $card = null;
        if ($siteId && $variantId) {
            $site = Site::findOrFail($siteId);
            abort_unless($user->canAccessSite($site), 403);
            $card = $this->buildCard($site, ItemVariant::with('item')->findOrFail($variantId), $from, $to);
        }

        return Inertia::render('reports/stock-card', [
            'sites' => $user->accessibleSites()->map->only('id', 'code', 'name'),
            'items' => $this->itemOptions(),
            'filters' => [
                'site_id' => $siteId,
                'item_variant_id' => $variantId,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'card' => $card,
        ]);
    }

    /**
     * Stream the stock card as a PDF styled like the F-INV-002 paper form.
     */
    public function pdf(Request $request)
    {
        abort_unless($request->user()->hasPermissionTo('reports.view'), 403);

        $site = Site::findOrFail($request->integer('site_id'));
        abort_unless($request->user()->canAccessSite($site), 403);

        $variant = ItemVariant::with('item')->findOrFail($request->integer('item_variant_id'));
        [$from, $to] = $this->parseRange($request);

        $card = $this->buildCard($site, $variant, $from, $to);

        $label = $from && $to
            ? $from->format('M j, Y').' – '.$to->format('M j, Y')
            : null;

        return Pdf::loadView('pdf.stock-card', [
                'card' => $card,
                'range' => ['label' => $label],
            ])
            ->setPaper('folio', 'landscape')
            ->stream("stock-card-{$variant->sku}.pdf");
    }

    /**
     * Export the stock card as CSV.
     */
    public function csv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($request->user()->hasPermissionTo('reports.view'), 403);

        $site = Site::findOrFail($request->integer('site_id'));
        abort_unless($request->user()->canAccessSite($site), 403);

        $variant = ItemVariant::with('item')->findOrFail($request->integer('item_variant_id'));
        [$from, $to] = $this->parseRange($request);
        $card = $this->buildCard($site, $variant, $from, $to);

        $rows = [['Date', 'DR/WS No.', 'Supplier / Other Projects', 'WS No.', 'Issued To', 'In', 'Out', 'Balance', 'Remarks']];
        foreach ($card['rows'] as $r) {
            $rows[] = [
                Carbon::parse($r['date'])->format('Y-m-d'),
                $r['in'] !== null ? ($r['dr_ws_no'] ?? '') : '',
                $r['in'] !== null ? $r['source_label'] : '',
                $r['out'] !== null ? ($r['dr_ws_no'] ?? '') : '',
                $r['issued_to'] ?? '',
                $r['in'], $r['out'], $r['balance'], $r['remarks'] ?? '',
            ];
        }

        return $this->streamCsv("stock-card-{$variant->sku}.csv", $rows);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function streamCsv(string $filename, array $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Optional inclusive date range filter.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function parseRange(Request $request): array
    {
        try {
            $from = $request->filled('from') ? Carbon::parse($request->string('from')->value())->startOfDay() : null;
            $to = $request->filled('to') ? Carbon::parse($request->string('to')->value())->endOfDay() : null;
        } catch (\Throwable) {
            return [null, null];
        }

        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCard(Site $site, ItemVariant $variant, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $header = SiteStock::where('site_id', $site->id)
            ->where('item_variant_id', $variant->id)
            ->first();

        // Balance carried into the period when a range is applied.
        $broughtForward = null;
        if ($from) {
            $bf = StockMovement::where('site_id', $site->id)
                ->where('item_variant_id', $variant->id)
                ->whereDate('movement_date', '<', $from->toDateString())
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END), 0) AS bal")
                ->value('bal');
            $broughtForward = ['date' => $from->toDateString(), 'balance' => (float) $bf];
        }

        $movements = StockMovement::where('site_id', $site->id)
            ->where('item_variant_id', $variant->id)
            ->when($from, fn ($q) => $q->whereDate('movement_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('movement_date', '<=', $to->toDateString()))
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
            'broughtForward' => $broughtForward,
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
