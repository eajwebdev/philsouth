<?php

namespace App\Http\Controllers;

use App\Models\DeliveryReceipt;
use App\Models\Item;
use App\Models\Site;
use Illuminate\Support\Collection;
use App\Services\NumberService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DeliveryReceipt::class);

        $receipts = DeliveryReceipt::query()
            ->forUser($request->user())
            ->with(['site:id,code,name', 'creator:id,name'])
            ->withCount('items')
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $s = $request->string('search')->value();
                $q->where(fn ($w) => $w->where('dr_no', 'like', "%{$s}%")->orWhere('supplier', 'like', "%{$s}%"));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('receiving/index', [
            'receipts' => $receipts,
            'filters' => [
                'search' => $request->string('search')->value(),
                'status' => $request->string('status')->value(),
            ],
            'can' => ['create' => $request->user()->can('create', DeliveryReceipt::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', DeliveryReceipt::class);

        return Inertia::render('receiving/create', [
            'sites' => $request->user()->accessibleSites()->map->only('id', 'code', 'name'),
            'items' => $this->itemOptions(),
        ]);
    }

    /**
     * Items with their stockable variants for the line-item picker.
     */
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

    public function store(Request $request, NumberService $numbers): RedirectResponse
    {
        $this->authorize('create', DeliveryReceipt::class);

        $data = $this->validateReceipt($request);
        $site = Site::findOrFail($data['site_id']);
        abort_unless($request->user()->canAccessSite($site), 403);

        $dr = DB::transaction(function () use ($data, $request, $numbers) {
            $dr = DeliveryReceipt::create([
                'dr_no' => $numbers->next('dr'),
                'site_id' => $data['site_id'],
                'source' => $data['source'],
                'supplier' => $data['supplier'] ?? null,
                'received_date' => $data['received_date'],
                'remarks' => $data['remarks'] ?? null,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $line) {
                $dr->items()->create($line);
            }

            return $dr;
        });

        return redirect()->route('receiving.show', $dr)->with('success', "Draft {$dr->dr_no} created.");
    }

    public function show(Request $request, DeliveryReceipt $receiving): Response
    {
        $this->authorize('view', $receiving);

        $receiving->load(['site:id,code,name', 'items.variant:id,item_id,sku,label,uom', 'items.variant.item:id,code,description,uom', 'creator:id,name']);

        return Inertia::render('receiving/show', [
            'receipt' => $receiving,
            'can' => [
                'post' => $request->user()->can('post', $receiving),
                'cancel' => $request->user()->can('cancel', $receiving),
            ],
        ]);
    }

    /**
     * Post the receipt: transition draft → posted and add IN movements.
     */
    public function post(Request $request, DeliveryReceipt $receiving, StockService $stock): RedirectResponse
    {
        $this->authorize('post', $receiving);

        $receiving->load('items.variant', 'site');

        DB::transaction(function () use ($receiving, $request, $stock) {
            foreach ($receiving->items as $line) {
                $stock->postMovement(
                    $receiving->site,
                    $line->variant,
                    'in',
                    $receiving->movementSource(),
                    (float) $line->quantity,
                    [
                        'reference' => $receiving,
                        'dr_ws_no' => $receiving->dr_no,
                        'movement_date' => $receiving->received_date->toDateString(),
                        'created_by' => $request->user()->id,
                        'remarks' => $receiving->source === 'other_project' ? 'From other project' : $receiving->supplier,
                    ],
                );
            }

            $receiving->update([
                'status' => 'posted',
                'received_by' => $request->user()->name,
            ]);
        });

        return back()->with('success', "{$receiving->dr_no} posted — stock received.");
    }

    public function cancel(DeliveryReceipt $receiving): RedirectResponse
    {
        $this->authorize('cancel', $receiving);

        $receiving->update(['status' => 'cancelled']);

        return back()->with('success', "{$receiving->dr_no} cancelled.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateReceipt(Request $request): array
    {
        return $request->validate([
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')],
            'source' => ['required', Rule::in(['supplier', 'other_project'])],
            'supplier' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('source') === 'supplier')],
            'received_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_variant_id' => ['required', 'integer', Rule::exists('item_variants', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
    }
}
