<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalSlipItem extends Model
{
    protected $fillable = [
        'withdrawal_slip_id',
        'item_variant_id',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
        ];
    }

    public function withdrawalSlip(): BelongsTo
    {
        return $this->belongsTo(WithdrawalSlip::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }
}
