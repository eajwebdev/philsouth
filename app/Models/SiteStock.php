<?php

namespace App\Models;

use App\Models\Concerns\ScopedBySite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteStock extends Model
{
    use ScopedBySite;

    protected $table = 'site_stock';

    protected $fillable = [
        'site_id',
        'item_variant_id',
        'location',
        'min_qty',
        'max_qty',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:2',
            'max_qty' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function isLowStock(): bool
    {
        return (float) $this->balance <= (float) $this->min_qty;
    }
}
