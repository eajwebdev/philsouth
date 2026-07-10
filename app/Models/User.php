<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Sites this user is assigned to (the site_user pivot).
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * IDs of the sites this user is assigned to.
     *
     * @return Collection<int, int>
     */
    public function siteIds(): Collection
    {
        return $this->sites()->pluck('sites.id');
    }

    /**
     * superadmin & administrator bypass site scope and see every site.
     */
    public function bypassesSiteScope(): bool
    {
        return $this->hasAnyRole(['superadmin', 'administrator']);
    }

    /**
     * Can this user touch the given site?
     */
    public function canAccessSite(Site $site): bool
    {
        if ($this->bypassesSiteScope()) {
            return true;
        }

        return $this->siteIds()->contains($site->id);
    }

    /**
     * Sites this user should see in pickers/switchers.
     *
     * @return Collection<int, Site>
     */
    public function accessibleSites(): Collection
    {
        if ($this->bypassesSiteScope()) {
            return Site::query()->where('is_active', true)->orderBy('name')->get();
        }

        return $this->sites()->where('is_active', true)->orderBy('name')->get();
    }

    public function isEngineer(): bool
    {
        return $this->hasRole('engineer');
    }

    public function isIcs(): bool
    {
        return $this->hasRole('ics');
    }
}
