<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferSlipItem extends Model
{
    protected $fillable = [
        'transfer_slip_id',
        'item_variant_id',
        'unit',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
        ];
    }

    public function transferSlip(): BelongsTo
    {
        return $this->belongsTo(TransferSlip::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }
}
