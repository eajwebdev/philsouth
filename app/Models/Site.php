<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Site extends Model
{
    /** @use HasFactory<\Database\Factories\SiteFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * All users assigned to this site (any role).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * Users on this site holding a given role.
     */
    public function usersWithRole(string $role): BelongsToMany
    {
        return $this->users()->whereHas('roles', fn ($q) => $q->where('name', $role));
    }

    public function engineers(): BelongsToMany
    {
        return $this->usersWithRole('engineer');
    }

    public function icsUsers(): BelongsToMany
    {
        return $this->usersWithRole('ics');
    }
}
