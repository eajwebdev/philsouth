<?php

namespace App\Models;

use App\Models\Concerns\ScopedBySite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use ScopedBySite;

    protected $fillable = [
        'site_id',
        'item_variant_id',
        'direction',
        'source',
        'reference_type',
        'reference_id',
        'dr_ws_no',
        'issued_to',
        'quantity',
        'balance_after',
        'movement_date',
        'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'movement_date' => 'date',
        ];
    }

    /** IN sources → the Monthly Summary column each maps to. */
    public const IN_SOURCES = ['purchase', 'warehouse_in', 'transfer_in'];

    /** OUT sources. */
    public const OUT_SOURCES = ['usage', 'transfer_out', 'loss_damage', 'return_supplier', 'warehouse_out', 'sale_other'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
