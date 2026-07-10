<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteStock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermissionTo('inventory.view'), 403);

        $user = $request->user();

        $siteId = $request->integer('site_id') ?: null;
        if ($siteId) {
            $site = Site::findOrFail($siteId);
            abort_unless($user->canAccessSite($site), 403);
        }

        $stock = SiteStock::query()
            ->forUser($user)
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($request->boolean('low_only'), fn ($q) => $q->whereColumn('balance', '<=', 'min_qty'))
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $s = $request->string('search')->value();
                $q->whereHas('variant', fn ($v) => $v
                    ->where('sku', 'like', "%{$s}%")
                    ->orWhere('barcode', 'like', "%{$s}%")
                    ->orWhereHas('item', fn ($i) => $i
                        ->where('code', 'like', "%{$s}%")
                        ->orWhere('description', 'like', "%{$s}%")));
            })
            ->with(['variant:id,item_id,sku,label,uom', 'variant.item:id,code,description,uom,category', 'site:id,code,name'])
            ->orderBy('site_id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('inventory/index', [
            'stock' => $stock,
            'sites' => $user->accessibleSites()->map->only('id', 'code', 'name'),
            'filters' => [
                'search' => $request->string('search')->value(),
                'site_id' => $siteId,
                'low_only' => $request->boolean('low_only'),
            ],
        ]);
    }
}
