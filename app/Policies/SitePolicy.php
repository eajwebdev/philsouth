<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.view') || $user->hasPermissionTo('sites.manage');
    }

    public function view(User $user, Site $site): bool
    {
        return $user->canAccessSite($site);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sites.manage');
    }

    public function update(User $user, Site $site): bool
    {
        return $user->hasPermissionTo('sites.manage');
    }

    public function delete(User $user, Site $site): bool
    {
        return $user->hasPermissionTo('sites.manage');
    }

    /**
     * Attaching/detaching engineers to a site — administrator only.
     */
    public function assignEngineer(User $user, Site $site): bool
    {
        return $user->hasPermissionTo('assign.engineer');
    }

    /**
     * Attaching/detaching ICS to a site — an engineer may only do this for
     * sites they are themselves assigned to. superadmin/administrator bypass.
     */
    public function assignIcs(User $user, Site $site): bool
    {
        if (! $user->hasPermissionTo('assign.ics') && ! $user->bypassesSiteScope()) {
            return false;
        }

        if ($user->bypassesSiteScope()) {
            return true;
        }

        return $user->siteIds()->contains($site->id);
    }
}
