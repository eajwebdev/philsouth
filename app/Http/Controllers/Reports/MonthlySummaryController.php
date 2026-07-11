<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Barryvdh\DomPDF\Facade\Pdf;
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
        [$from, $to] = $this->resolveRange($request);

        $summary = null;
        if ($siteId) {
            $site = Site::findOrFail($siteId);
            abort_unless($user->canAccessSite($site), 403);
            $summary = $this->buildSummary($site, $from, $to);
        }

        return Inertia::render('reports/monthly-summary', [
            'sites' => $user->accessibleSites()->map->only('id', 'code', 'name'),
            'filters' => ['site_id' => $siteId, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => $summary,
        ]);
    }

    /**
     * Stream the summary as a PDF styled like the F-INV-006 paper form
     * (landscape, U.O.M. column, Prepared by / Checked by signature lines).
     */
    public function pdf(Request $request)
    {
        abort_unless($request->user()->hasPermissionTo('reports.view'), 403);

        $site = Site::findOrFail($request->integer('site_id'));
        abort_unless($request->user()->canAccessSite($site), 403);

        [$from, $to] = $this->resolveRange($request);
        $summary = $this->buildSummary($site, $from, $to);

        return Pdf::loadView('pdf.monthly-summary', [
                'summary' => $summary,
                'preparedBy' => $request->user()->name,
            ])
            ->setPaper('folio', 'landscape')
            ->stream("inventory-summary-{$site->code}-{$from->toDateString()}_{$to->toDateString()}.pdf");
    }

    /**
     * Export the summary as CSV.
     */
    public function csv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($request->user()->hasPermissionTo('reports.view'), 403);

        $site = Site::findOrFail($request->integer('site_id'));
        abort_unless($request->user()->canAccessSite($site), 403);

        [$from, $to] = $this->resolveRange($request);
        $summary = $this->buildSummary($site, $from, $to);

        $rows = [[
            'Item Description', 'U.O.M.', 'Beginning', 'Purchases', 'Warehouse In', 'Transfer In',
            'Usage', 'Transfer Out', 'Loss & Damages', 'Return to Supplier', 'Warehouse Out', 'Sales/Other', 'Ending',
        ]];
        foreach ($summary['rows'] as $r) {
            $desc = $r['variant']['description'].($r['variant']['label'] ? ' — '.$r['variant']['label'] : '');
            $rows[] = [
                $desc, $r['variant']['uom'], $r['beginning'], $r['purchases'], $r['warehouse_in'], $r['transfer_in'],
                $r['usage'], $r['transfer_out'], $r['loss_damage'], $r['return_supplier'], $r['warehouse_out'], $r['sale_other'], $r['ending'],
            ];
        }

        $name = "inventory-summary-{$site->code}-{$from->toDateString()}_{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /**
     * Resolve the reporting window from the request. Defaults to the current
     * calendar month. Falls back gracefully if only one bound is supplied.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveRange(Request $request): array
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? (clone $from)->endOfMonth();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSummary(Site $site, Carbon $start, Carbon $end): array
    {
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
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'period_label' => $start->isSameDay($end)
                ? $start->format('M j, Y')
                : $start->format('M j, Y').' – '.$end->format('M j, Y'),
            'is_closed' => $isClosed,
            'reconciles' => $reconciles,
            'rows' => $rows,
        ];
    }
}
