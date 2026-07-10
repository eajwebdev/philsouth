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
            ->paginate(20)
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

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Item::class);

        // The model auto-creates the default variant on create.
        Item::create($this->validateItem($request));

        return back()->with('success', 'Item created.');
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $item->update($this->validateItem($request, $item));

        return back()->with('success', 'Item updated.');
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
