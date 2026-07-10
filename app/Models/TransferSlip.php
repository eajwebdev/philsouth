<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransferSlip extends Model
{
    protected $fillable = [
        'ts_no',
        'from_site_id',
        'to_site_id',
        'date',
        'time_delivered',
        'delivered_to',
        'delivered_by',
        'vehicle_plate',
        'status',
        'date_received',
        'time_received',
        'received_by',
        'dispatched_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'date_received' => 'date',
            'dispatched_at' => 'datetime',
        ];
    }

    /**
     * A transfer touches two sites; a user may see it from either end.
     * superadmin / administrator bypass the scope.
     */
    public function scopeForUser(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->bypassesSiteScope()) {
            return $query;
        }

        $ids = $user->siteIds();

        return $query->where(fn ($q) => $q
            ->whereIn('from_site_id', $ids)
            ->orWhereIn('to_site_id', $ids));
    }

    public function fromSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'from_site_id');
    }

    public function toSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'to_site_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferSlipItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isInTransit(): bool
    {
        return $this->status === 'in_transit';
    }
}
