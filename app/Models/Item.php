<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    /** @use HasFactory<\Database\Factories\ItemFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'uom',
        'category',
        'has_variants',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_variants' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Every item gets one default variant so the stockable unit is uniform.
        static::created(function (Item $item) {
            if (! $item->variants()->exists()) {
                $item->variants()->create([
                    'sku' => $item->code,
                    'label' => null,
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }
        });
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class);
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ItemVariant::class)->where('is_default', true);
    }
}
