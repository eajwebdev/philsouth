<?php

namespace App\Policies;

use App\Models\DeliveryReceipt;
use App\Models\User;

class DeliveryReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('receiving.manage') || $user->hasPermissionTo('inventory.view');
    }

    public function view(User $user, DeliveryReceipt $dr): bool
    {
        return $user->canAccessSite($dr->site);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('receiving.manage');
    }

    public function update(User $user, DeliveryReceipt $dr): bool
    {
        return $dr->isDraft()
            && $user->hasPermissionTo('receiving.manage')
            && $user->canAccessSite($dr->site);
    }

    public function delete(User $user, DeliveryReceipt $dr): bool
    {
        // Drafts only — posted receipts are part of the stock ledger.
        return $this->update($user, $dr);
    }

    public function post(User $user, DeliveryReceipt $dr): bool
    {
        return $this->update($user, $dr);
    }

    public function cancel(User $user, DeliveryReceipt $dr): bool
    {
        // Cancel only while draft.
        return $this->update($user, $dr);
    }
}
