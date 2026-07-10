<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.view') || $user->hasPermissionTo('items.manage');
    }

    public function view(User $user, Item $item): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('items.manage');
    }

    public function update(User $user, Item $item): bool
    {
        return $user->hasPermissionTo('items.manage');
    }

    public function delete(User $user, Item $item): bool
    {
        return $user->hasPermissionTo('items.manage');
    }
}
