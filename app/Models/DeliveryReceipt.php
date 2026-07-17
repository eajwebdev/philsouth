<?php

namespace App\Models;

use App\Models\Concerns\ScopedBySite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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

    /**
     * Map the receipt source to a stock-movement IN source. Supplier deliveries
     * post as 'purchase'; everything else (another project, or a generic other
     * source) is an internal 'warehouse_in'.
     */
    public function movementSource(): string
    {
        return $this->source === 'supplier' ? 'purchase' : 'warehouse_in';
    }

    /** Human label for the source (used on the receipt + ledger remarks). */
    public function sourceLabel(): string
    {
        return match ($this->source) {
            'supplier' => $this->supplier ?: 'Supplier',
            'other_project' => 'Other project',
            'other' => $this->supplier ?: 'Other source',
            default => ucfirst(str_replace('_', ' ', $this->source)),
        };
    }

    /** Where the actor was when this record's actions happened. */
    public function locationStamps(): MorphMany
    {
        return $this->morphMany(LocationStamp::class, 'stampable');
    }
}
