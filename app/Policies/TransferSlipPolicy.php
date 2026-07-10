<?php

namespace App\Policies;

use App\Models\TransferSlip;
use App\Models\User;

class TransferSlipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['transfer.create', 'transfer.receive', 'inventory.view']);
    }

    /** Visible from either end of the transfer. */
    public function view(User $user, TransferSlip $ts): bool
    {
        return $user->canAccessSite($ts->fromSite) || $user->canAccessSite($ts->toSite);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('transfer.create');
    }

    public function update(User $user, TransferSlip $ts): bool
    {
        return $ts->isDraft()
            && $user->hasPermissionTo('transfer.create')
            && $user->canAccessSite($ts->fromSite);
    }

    public function delete(User $user, TransferSlip $ts): bool
    {
        return $this->update($user, $ts);
    }

    /** Dispatch (draft -> in_transit): ICS at the ORIGIN site. */
    public function dispatch(User $user, TransferSlip $ts): bool
    {
        return $ts->isDraft()
            && $user->hasPermissionTo('transfer.create')
            && $user->canAccessSite($ts->fromSite);
    }

    /** Receive (in_transit -> received): ICS at the DESTINATION site. */
    public function receive(User $user, TransferSlip $ts): bool
    {
        return $ts->isInTransit()
            && $user->hasPermissionTo('transfer.receive')
            && $user->canAccessSite($ts->toSite);
    }

    public function cancel(User $user, TransferSlip $ts): bool
    {
        return $ts->isDraft()
            && $user->hasPermissionTo('transfer.create')
            && $user->canAccessSite($ts->fromSite);
    }
}
