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
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $s = $request->string('search')->value();
                $q->where(fn ($w) => $w
                    ->where('code', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhere('barcode', 'like', "%{$s}%")
                    ->orWhere('category', 'like', "%{$s}%"));
            })
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('items/index', [
            'items' => $items,
            'filters' => ['search' => $request->string('search')->value()],
            'can' => [
                'manage' => $request->user()->can('create', Item::class),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Item::class);

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

        if ($item->movements()->exists()) {
            return back()->with('error', 'Cannot delete an item that has stock movements. Deactivate it instead.');
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
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('items', 'barcode')->ignore($item?->id)],
            'is_active' => ['boolean'],
        ]);
    }
}
