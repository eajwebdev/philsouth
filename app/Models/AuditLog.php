<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id', 'site_id', 'description', 'properties',
    ];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    /**
     * Record an audit entry for the current actor. Safe to call inside a
     * transaction — a logging failure must never break the business action.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $subject
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $action, ?Model $subject = null, ?string $description = null, array $properties = [], ?int $siteId = null): void
    {
        try {
            static::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'subject_type' => $subject ? $subject->getMorphClass() : null,
                'subject_id' => $subject?->getKey(),
                'site_id' => $siteId ?? ($subject->site_id ?? null),
                'description' => $description,
                'properties' => $properties ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
