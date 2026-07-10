<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The stockable unit (SKU). Simple items carry a single is_default variant;
 * variant items carry several. site_stock and stock_movements key on this.
 */
class ItemVariant extends Model
{
    /** @use HasFactory<\Database\Factories\ItemVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'item_id',
        'sku',
        'label',
        'attributes',
        'barcode',
        'uom',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function siteStocks(): HasMany
    {
        return $this->hasMany(SiteStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Variant's own uom, falling back to the parent item's. */
    public function effectiveUom(): string
    {
        return $this->uom ?: ($this->item?->uom ?? '');
    }

    /** Human label: the item description plus the variant label when present. */
    public function displayName(): string
    {
        $base = $this->item?->description ?? $this->sku;

        return $this->label ? "{$base} — {$this->label}" : $base;
    }
}
