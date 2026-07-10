<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a `forUser` scope that limits a query to the sites a user may access.
 * superadmin / administrator bypass the scope and see everything.
 *
 * The model using this trait must have a `site_id` column.
 */
trait ScopedBySite
{
    public function scopeForUser(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->bypassesSiteScope()) {
            return $query;
        }

        return $query->whereIn($this->getTable().'.site_id', $user->siteIds());
    }
}
