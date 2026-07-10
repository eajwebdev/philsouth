<?php

namespace App\Http\Controllers;

use App\Models\ItemVariant;
use App\Models\Site;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    /**
     * Resolve a scanned barcode/QR payload to a variant. Optionally include
     * the current balance at a site. Returns JSON (not an Inertia page) so it
     * can be called inline from scan-driven flows.
     */
    public function lookup(Request $request, StockService $stock): JsonResponse
    {
        $barcode = trim((string) $request->query('barcode'));
        $variantId = $request->integer('variant_id') ?: null;

        if ($barcode === '' && ! $variantId) {
            return response()->json(['found' => false]);
        }

        $variant = ItemVariant::query()
            ->where('is_active', true)
            ->when($variantId, fn ($q) => $q->whereKey($variantId), fn ($q) => $q->where('barcode', $barcode))
            ->with('item:id,code,description,uom')
            ->first();

        if (! $variant) {
            return response()->json(['found' => false, 'barcode' => $barcode]);
        }

        $payload = [
            'found' => true,
            'variant' => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'label' => $variant->label,
                'uom' => $variant->effectiveUom(),
                'barcode' => $variant->barcode,
                'item' => $variant->item->only('id', 'code', 'description'),
            ],
        ];

        $siteId = $request->integer('site_id') ?: null;
        if ($siteId) {
            $site = Site::find($siteId);
            if ($site && $request->user()->canAccessSite($site)) {
                $payload['balance'] = $stock->balance($site, $variant);
            }
        }

        return response()->json($payload);
    }
}
