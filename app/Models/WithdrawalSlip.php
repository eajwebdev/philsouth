<?php

namespace App\Models;

use App\Models\Concerns\ScopedBySite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WithdrawalSlip extends Model
{
    use ScopedBySite;

    protected $fillable = [
        'ws_no',
        'project_code',
        'site_id',
        'date',
        'time',
        'requested_by_type',
        'requested_by_other',
        'delivered_to',
        'remarks',
        'status',
        'prepared_by',
        'approved_by',
        'released_by',
        'received_by',
        'approved_at',
        'released_at',
        'received_at',
        'reject_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'approved_at' => 'datetime',
            'released_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WithdrawalSlipItem::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }

    /** Where the actor was when this record's actions happened. */
    public function locationStamps(): MorphMany
    {
        return $this->morphMany(LocationStamp::class, 'stampable');
    }
}
