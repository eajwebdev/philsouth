<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The single place stock balances change. Never mutate site_stock.balance
 * anywhere else — every receiving / release / transfer posts through here so
 * the Stock Card and Monthly Summary always reconcile.
 */
class StockService
{
    /**
     * Post a stock movement and update the running balance atomically.
     *
     * @param  'in'|'out'  $direction
     * @param  array{
     *     reference?: Model|null,
     *     dr_ws_no?: string|null,
     *     issued_to?: string|null,
     *     movement_date?: string|\DateTimeInterface|null,
     *     remarks?: string|null,
     *     created_by?: int|null,
     * }  $meta
     */
    public function postMovement(
        Site $site,
        Item $item,
        string $direction,
        string $source,
        float $qty,
        array $meta = [],
    ): StockMovement {
        if (! in_array($direction, ['in', 'out'], true)) {
            throw new InvalidArgumentException("Invalid direction [{$direction}].");
        }

        if ($qty <= 0) {
            throw new InvalidArgumentException('Movement quantity must be positive.');
        }

        return DB::transaction(function () use ($site, $item, $direction, $source, $qty, $meta) {
            // 1. Lock (or create) the site_stock row for (site, item).
            $stock = SiteStock::query()
                ->where('site_id', $site->id)
                ->where('item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = SiteStock::create([
                    'site_id' => $site->id,
                    'item_id' => $item->id,
                    'balance' => 0,
                ]);
                // Re-lock the freshly created row.
                $stock = SiteStock::query()->whereKey($stock->id)->lockForUpdate()->first();
            }

            $current = (float) $stock->balance;

            // 2. Guard OUT movements against overdraw (unless allow-negative).
            if ($direction === 'out' && ! config('inventory.allow_negative') && $qty > $current) {
                throw new RuntimeException(
                    "Insufficient stock for {$item->code} at {$site->code}: ".
                    "requested {$qty}, available {$current}."
                );
            }

            // 3. Compute the new balance.
            $balanceAfter = $direction === 'in' ? $current + $qty : $current - $qty;

            // 4. Update the running balance.
            $stock->balance = $balanceAfter;
            $stock->save();

            // 5. Insert the ledger row.
            $reference = $meta['reference'] ?? null;

            return StockMovement::create([
                'site_id' => $site->id,
                'item_id' => $item->id,
                'direction' => $direction,
                'source' => $source,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'dr_ws_no' => $meta['dr_ws_no'] ?? null,
                'issued_to' => $meta['issued_to'] ?? null,
                'quantity' => $qty,
                'balance_after' => $balanceAfter,
                'movement_date' => $meta['movement_date'] ?? now()->toDateString(),
                'remarks' => $meta['remarks'] ?? null,
                'created_by' => $meta['created_by'] ?? Auth::id(),
            ]);
        });
    }

    /**
     * Current balance for a (site, item) pair.
     */
    public function balance(Site $site, Item $item): float
    {
        return (float) (SiteStock::query()
            ->where('site_id', $site->id)
            ->where('item_id', $item->id)
            ->value('balance') ?? 0);
    }
}
