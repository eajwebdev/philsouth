<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'sites.manage',
            'users.manage',
            'roles.manage',
            'assign.engineer',
            'assign.ics',
            'items.manage',
            'receiving.manage',
            'withdrawal.create',
            'withdrawal.approve',
            'withdrawal.release',
            'withdrawal.receive',
            'transfer.create',
            'transfer.receive',
            'reports.view',
            'inventory.view',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $map = [
            'superadmin' => $permissions, // also covered by Gate::before
            'administrator' => [
                'sites.manage', 'users.manage', 'assign.engineer',
                'items.manage', 'reports.view', 'inventory.view',
            ],
            'engineer' => [
                'assign.ics', 'withdrawal.approve', 'reports.view', 'inventory.view',
                // Engineers can maintain the item catalog and receive deliveries on their sites.
                'items.manage', 'receiving.manage',
            ],
            'ics' => [
                'receiving.manage', 'withdrawal.create', 'withdrawal.release',
                'withdrawal.receive', 'transfer.create', 'transfer.receive',
                'reports.view', 'inventory.view',
                // ICS can add catalog items (e.g. while encoding a delivery receipt).
                'items.manage',
            ],
        ];

        foreach ($map as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($rolePermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
