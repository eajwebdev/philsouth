<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemVariantController extends Controller
{
    public function store(Request $request, Item $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $data = $this->validateVariant($request);

        $item->variants()->create([
            ...$data,
            'is_default' => false,
        ]);

        return back()->with('success', 'Variant added.');
    }

    public function update(Request $request, Item $item, ItemVariant $variant): RedirectResponse
    {
        $this->authorize('update', $item);
        abort_unless($variant->item_id === $item->id, 404);

        $variant->update($this->validateVariant($request, $variant));

        return back()->with('success', 'Variant updated.');
    }

    public function setDefault(Item $item, ItemVariant $variant): RedirectResponse
    {
        $this->authorize('update', $item);
        abort_unless($variant->item_id === $item->id, 404);

        $item->variants()->update(['is_default' => false]);
        $variant->update(['is_default' => true]);

        return back()->with('success', "{$variant->sku} is now the default variant.");
    }

    public function destroy(Item $item, ItemVariant $variant): RedirectResponse
    {
        $this->authorize('update', $item);
        abort_unless($variant->item_id === $item->id, 404);

        if ($variant->movements()->exists()) {
            return back()->with('error', 'Cannot delete a variant with stock movements. Deactivate it instead.');
        }

        if ($item->variants()->count() <= 1) {
            return back()->with('error', 'An item must keep at least one variant.');
        }

        $wasDefault = $variant->is_default;
        $variant->delete();

        // Promote another variant to default if we removed the default one.
        if ($wasDefault) {
            $item->variants()->where('is_active', true)->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Variant deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateVariant(Request $request, ?ItemVariant $variant = null): array
    {
        return $request->validate([
            'sku' => ['required', 'string', 'max:60', Rule::unique('item_variants', 'sku')->ignore($variant?->id)],
            'label' => ['nullable', 'string', 'max:120'],
            'attributes' => ['nullable', 'array'],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('item_variants', 'barcode')->ignore($variant?->id)],
            'uom' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);
    }
}
