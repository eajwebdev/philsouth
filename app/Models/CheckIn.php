<?php

namespace App\Models;

use App\Models\Concerns\ScopedBySite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    use ScopedBySite;

    protected $fillable = [
        'site_id', 'user_id', 'latitude', 'longitude', 'accuracy_m', 'unavailable_reason', 'note',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_m' => 'decimal:2',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasFix(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
