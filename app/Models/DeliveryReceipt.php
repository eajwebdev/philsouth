<?php

namespace App\Models;

use App\Models\Concerns\ScopedBySite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryReceipt extends Model
{
    use ScopedBySite;

    protected $fillable = [
        'dr_no',
        'site_id',
        'source',
        'supplier',
        'received_date',
        'remarks',
        'status',
        'received_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryReceiptItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Supplier deliveries post as 'purchase'; other-project as 'warehouse_in'. */
    public function movementSource(): string
    {
        return $this->source === 'supplier' ? 'purchase' : 'warehouse_in';
    }
}
