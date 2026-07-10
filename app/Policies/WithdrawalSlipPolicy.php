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

    /** ICS submits a draft for approval. */
    public function submit(User $user, WithdrawalSlip $ws): bool
    {
        return $ws->isDraft()
            && $user->hasPermissionTo('withdrawal.create')
            && $user->canAccessSite($ws->site);
    }

    /**
     * Approve / reject: an engineer assigned to the slip's site.
     * (superadmin bypasses via Gate::before.)
     */
    public function approve(User $user, WithdrawalSlip $ws): bool
    {
        return $ws->isPending()
            && $user->hasPermissionTo('withdrawal.approve')
            && $user->canAccessSite($ws->site);
    }

    public function reject(User $user, WithdrawalSlip $ws): bool
    {
        return $this->approve($user, $ws);
    }

    /** NO RELEASE WITHOUT APPROVAL — only an approved slip may be released. */
    public function release(User $user, WithdrawalSlip $ws): bool
    {
        return $ws->isApproved()
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
