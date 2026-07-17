<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WithdrawalSlip;

class WithdrawalSlipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'withdrawal.create', 'withdrawal.approve', 'withdrawal.release',
            'withdrawal.receive', 'inventory.view',
        ]);
    }

    public function view(User $user, WithdrawalSlip $ws): bool
    {
        return $user->canAccessSite($ws->site);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('withdrawal.create');
    }

    /** Edit / delete only while still a draft. */
    public function update(User $user, WithdrawalSlip $ws): bool
    {
        return $ws->isDraft()
            && $user->hasPermissionTo('withdrawal.create')
            && $user->canAccessSite($ws->site);
    }

    public function delete(User $user, WithdrawalSlip $ws): bool
    {
        return $this->update($user, $ws);
    }

    /**
     * No approval step: a draft may be released directly. Legacy slips still
     * sitting in pending_approval/approved can also be released.
     */
    public function release(User $user, WithdrawalSlip $ws): bool
    {
        return in_array($ws->status, ['draft', 'pending_approval', 'approved'], true)
            && $user->hasPermissionTo('withdrawal.release')
            && $user->canAccessSite($ws->site);
    }

    public function receive(User $user, WithdrawalSlip $ws): bool
    {
        return $ws->isReleased()
            && $user->hasPermissionTo('withdrawal.receive')
            && $user->canAccessSite($ws->site);
    }

    public function cancel(User $user, WithdrawalSlip $ws): bool
    {
        return in_array($ws->status, ['draft', 'pending_approval'], true)
            && $user->hasPermissionTo('withdrawal.create')
            && $user->canAccessSite($ws->site);
    }
}
