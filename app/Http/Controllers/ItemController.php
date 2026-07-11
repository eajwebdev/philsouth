<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Item::class);

        $items = Item::query()
            ->withCount('variants')
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $s = $request->string('search')->value();
                $q->where(fn ($w) => $w
                    ->where('code', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhere('category', 'like', "%{$s}%")
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$s}%")->orWhere('barcode', 'like', "%{$s}%")));
            })
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('items/index', [
            'items' => $items,
            'filters' => ['search' => $request->string('search')->value()],
            'can' => ['manage' => $request->user()->can('create', Item::class)],
        ]);
    }

    public function show(Request $request, Item $item): Response
    {
        $this->authorize('view', $item);

        $item->load(['variants' => fn ($q) => $q->orderByDesc('is_default')->orderBy('sku')]);
        $item->loadCount('variants');

        return Inertia::render('items/show', [
            'item' => $item,
            'can' => ['manage' => $request->user()->can('update', $item)],
        ]);
    }

    public function labels(Request $request, Item $item): Response
    {
        $this->authorize('view', $item);

        $item->load(['variants' => fn ($q) => $q->where('is_active', true)->orderByDesc('is_default')->orderBy('sku')]);

        return Inertia::render('items/labels', [
            'item' => $item->only('id', 'code', 'description', 'uom'),
            'variants' => $item->variants->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'label' => $v->label,
                'barcode' => $v->barcode,
                'payload' => $v->barcode ?: $v->sku,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Item::class);

        // The model auto-creates the default variant on create.
        Item::create($this->validateItem($request));

        return back()->with('success', 'Item created.');
    }

    /**
     * JSON quick-create used inline from receiving/withdrawal/transfer forms,
     * so an ICS or engineer can add every item on a paper receipt on the spot.
     * Items are global: the new item is visible to ALL sites with 0 stock.
     */
    public function quickStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Item::class);

        // Code is optional here — auto-generate the next ITM-#### when omitted.
        if ($request->string('code')->isEmpty()) {
            $last = Item::where('code', 'like', 'ITM-%')
                ->orderByRaw('LENGTH(code) DESC')
                ->orderByDesc('code')
                ->value('code');
            $next = $last ? ((int) substr($last, 4)) + 1 : 1;
            $request->merge(['code' => sprintf('ITM-%04d', $next)]);
        }

        $item = Item::create($this->validateItem($request));
        $item->load(['variants' => fn ($q) => $q->orderByDesc('is_default')->orderBy('sku')]);

        return response()->json([
            'item' => [
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
            ],
        ], 201);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $item->update($this->validateItem($request, $item));

        return back()->with('success', 'Item updated.');
    }

    /**
     * Bulk-create items from an uploaded CSV. Columns (header row required):
     * code, description, uom, category. Existing codes are skipped.
     */
    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Item::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (! $handle) {
            return back()->with('error', 'Could not read the uploaded file.');
        }

        $header = null;
        $created = 0;
        $skipped = 0;
        $errors = 0;

        while (($row = fgetcsv($handle)) !== false) {
            // First non-empty row is the header.
            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $row);
                continue;
            }
            if (count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            $data = array_combine($header, array_pad($row, count($header), null));
            $code = trim((string) ($data['code'] ?? ''));
            $description = trim((string) ($data['description'] ?? ''));
            $uom = trim((string) ($data['uom'] ?? ''));

            if ($code === '' || $description === '' || $uom === '') {
                $errors++;
                continue;
            }
            if (Item::where('code', $code)->exists()) {
                $skipped++;
                continue;
            }

            Item::create([
                'code' => $code,
                'description' => $description,
                'uom' => $uom,
                'category' => trim((string) ($data['category'] ?? '')) ?: null,
            ]);
            $created++;
        }
        fclose($handle);

        $msg = "Imported {$created} item(s).".
            ($skipped ? " Skipped {$skipped} existing." : '').
            ($errors ? " {$errors} row(s) had missing code/description/uom." : '');

        return back()->with($created > 0 ? 'success' : 'error', $msg);
    }

    public function destroy(Item $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $hasMovements = $item->variants()->whereHas('movements')->exists();
        if ($hasMovements) {
            return back()->with('error', 'Cannot delete an item with stock movements. Deactivate it instead.');
        }

        $item->delete();

        return back()->with('success', 'Item deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateItem(Request $request, ?Item $item = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('items', 'code')->ignore($item?->id)],
            'description' => ['required', 'string', 'max:255'],
            'uom' => ['required', 'string', 'max:20'],
            'category' => ['nullable', 'string', 'max:100'],
            'has_variants' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }
}
