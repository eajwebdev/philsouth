<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

class LocationStamp extends Model
{
    protected $fillable = [
        'stampable_type', 'stampable_id', 'action', 'user_id',
        'latitude', 'longitude', 'accuracy_m', 'unavailable_reason', 'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_m' => 'decimal:2',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * Validation rules for the geo payload a form posts alongside an action.
     * Everything is optional — capture must never block the business action.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'unavailable_reason' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Stamp a record with wherever the actor was. Best-effort: a bad payload
     * must never break the action it accompanies.
     */
    public static function capture(Request $request, Model $subject, string $action): void
    {
        try {
            $lat = $request->input('latitude');
            $lng = $request->input('longitude');

            // Nothing at all to record — don't create a noise row.
            if ($lat === null && $lng === null && ! $request->filled('unavailable_reason')) {
                return;
            }

            static::create([
                'stampable_type' => $subject->getMorphClass(),
                'stampable_id' => $subject->getKey(),
                'action' => $action,
                'user_id' => $request->user()?->id,
                'latitude' => $lat !== null ? (float) $lat : null,
                'longitude' => $lng !== null ? (float) $lng : null,
                'accuracy_m' => $request->input('accuracy_m') !== null ? (float) $request->input('accuracy_m') : null,
                'unavailable_reason' => $request->input('unavailable_reason'),
                'captured_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function stampable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasFix(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Serialize a record's stamps for the front-end.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function forRecord(Model $subject): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('stampable_type', $subject->getMorphClass())
            ->where('stampable_id', $subject->getKey())
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (self $s) => [
                'id' => $s->id,
                'action' => $s->action,
                'user' => $s->user?->name,
                'latitude' => $s->latitude !== null ? (float) $s->latitude : null,
                'longitude' => $s->longitude !== null ? (float) $s->longitude : null,
                'accuracy_m' => $s->accuracy_m !== null ? (float) $s->accuracy_m : null,
                'unavailable_reason' => $s->unavailable_reason,
                'at' => $s->captured_at?->toIso8601String(),
            ]);
    }
}
