<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Site;
use App\Models\TransferSlip;
use App\Services\NumberService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransferSlipController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TransferSlip::class);

        $transfers = TransferSlip::query()
            ->forUser($request->user())
            ->with(['fromSite:id,code,name', 'toSite:id,code,name', 'creator:id,name'])
            ->withCount('items')
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where('ts_no', 'like', '%'.$request->string('search')->value().'%'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('transfers/index', [
            'transfers' => $transfers,
            'filters' => [
                'search' => $request->string('search')->value(),
                'status' => $request->string('status')->value(),
            ],
            'can' => ['create' => $request->user()->can('create', TransferSlip::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', TransferSlip::class);

        return Inertia::render('transfers/create', [
            // Origin must be a site the user operates.
            'fromSites' => $request->user()->accessibleSites()->map->only('id', 'code', 'name'),
            // Destination can be any active site.
            'toSites' => Site::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'items' => $this->itemOptions(),
        ]);
    }

    public function store(Request $request, NumberService $numbers): RedirectResponse
    {
        $this->authorize('create', TransferSlip::class);

        $data = $this->validateSlip($request);

        $fromSite = Site::findOrFail($data['from_site_id']);
        abort_unless($request->user()->canAccessSite($fromSite), 403);

        $ts = DB::transaction(function () use ($data, $request, $numbers) {
            $ts = TransferSlip::create([
                'ts_no' => $numbers->next('ts'),
                'from_site_id' => $data['from_site_id'],
                'to_site_id' => $data['to_site_id'],
                'date' => $data['date'],
                'delivered_to' => $data['delivered_to'] ?? null,
                'delivered_by' => $data['delivered_by'] ?? null,
                'vehicle_plate' => $data['vehicle_plate'] ?? null,
                'time_delivered' => $data['time_delivered'] ?? null,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $line) {
                $ts->items()->create($line);
            }

            return $ts;
        });

        return redirect()->route('transfers.show', $ts)->with('success', "Draft {$ts->ts_no} created.");
    }

    public function show(Request $request, TransferSlip $transfer): Response
    {
        $this->authorize('view', $transfer);

        $transfer->load([
            'fromSite:id,code,name',
            'toSite:id,code,name',
            'items.variant:id,item_id,sku,label,uom',
            'items.variant.item:id,code,description,uom',
            'creator:id,name',
        ]);

        $user = $request->user();

        return Inertia::render('transfers/show', [
            'transfer' => $transfer,
            'can' => [
                'dispatch' => $user->can('dispatch', $transfer),
                'receive' => $user->can('receive', $transfer),
                'cancel' => $user->can('cancel', $transfer),
            ],
        ]);
    }

    /**
     * Dispatch: draft -> in_transit. Posts OUT transfer_out at the origin.
     */
    public function dispatchTransfer(TransferSlip $transfer, Request $request, StockService $stock): RedirectResponse
    {
        $this->authorize('dispatch', $transfer);

        $transfer->load('items.variant', 'fromSite');

        try {
            DB::transaction(function () use ($transfer, $request, $stock) {
                foreach ($transfer->items as $line) {
                    $stock->postMovement(
                        $transfer->fromSite,
                        $line->variant,
                        'out',
                        'transfer_out',
                        (float) $line->qty,
                        [
                            'reference' => $transfer,
                            'dr_ws_no' => $transfer->ts_no,
                            'movement_date' => $transfer->date->toDateString(),
                            'created_by' => $request->user()->id,
                            'remarks' => 'Transfer to '.$transfer->toSite->code,
                        ],
                    );
                }

                $transfer->update(['status' => 'in_transit', 'dispatched_at' => now()]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$transfer->ts_no} dispatched — stock is in transit.");
    }

    /**
     * Receive: in_transit -> received. Posts IN transfer_in at the destination.
     */
    public function receive(TransferSlip $transfer, Request $request, StockService $stock): RedirectResponse
    {
        $this->authorize('receive', $transfer);

        $data = $request->validate([
            'received_by' => ['nullable', 'string', 'max:255'],
            'time_received' => ['nullable', 'string', 'max:20'],
        ]);

        $transfer->load('items.variant', 'toSite');

        DB::transaction(function () use ($transfer, $request, $stock, $data) {
            foreach ($transfer->items as $line) {
                $stock->postMovement(
                    $transfer->toSite,
                    $line->variant,
                    'in',
                    'transfer_in',
                    (float) $line->qty,
                    [
                        'reference' => $transfer,
                        'dr_ws_no' => $transfer->ts_no,
                        'movement_date' => now()->toDateString(),
                        'created_by' => $request->user()->id,
                        'remarks' => 'Transfer from '.$transfer->fromSite->code,
                    ],
                );
            }

            $transfer->update([
                'status' => 'received',
                'date_received' => now()->toDateString(),
                'time_received' => $data['time_received'] ?? now()->format('H:i'),
                'received_by' => $data['received_by'] ?? $request->user()->name,
            ]);
        });

        return back()->with('success', "{$transfer->ts_no} received — stock added at destination.");
    }

    public function cancel(TransferSlip $transfer): RedirectResponse
    {
        $this->authorize('cancel', $transfer);

        $transfer->update(['status' => 'cancelled']);

        return back()->with('success', "{$transfer->ts_no} cancelled.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateSlip(Request $request): array
    {
        return $request->validate([
            'from_site_id' => ['required', 'integer', Rule::exists('sites', 'id')],
            'to_site_id' => ['required', 'integer', 'different:from_site_id', Rule::exists('sites', 'id')],
            'date' => ['required', 'date'],
            'time_delivered' => ['nullable', 'string', 'max:20'],
            'delivered_to' => ['nullable', 'string', 'max:255'],
            'delivered_by' => ['nullable', 'string', 'max:255'],
            'vehicle_plate' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_variant_id' => ['required', 'integer', Rule::exists('item_variants', 'id')],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);
    }

    protected function itemOptions(): Collection
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
