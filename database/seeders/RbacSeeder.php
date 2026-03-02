<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'platform.tenants.manage',
            'platform.billing.manage',
            'platform.support.manage',
            'organizations.manage',
            'branches.manage',
            'users.manage',
            'roles.manage',
            'units.manage',
            'suppliers.manage',
            'products.manage',
            'inventory.view',
            'inventory.adjust',
            'purchase.manage',
            'sales.pos',
            'sales.return',
            'counter.session.manage',
            'reports.view',
            'audit.view',
        ];

        $permissionModels = [];
        foreach ($permissions as $permission) {
            $permissionModels[$permission] = Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'super_admin' => $permissions,
            'platform_support' => ['platform.support.manage', 'platform.tenants.manage'],
            'finance_admin' => ['platform.billing.manage', 'reports.view'],
            'org_owner' => [
                'organizations.manage',
                'branches.manage',
                'users.manage',
                'roles.manage',
                'units.manage',
                'suppliers.manage',
                'products.manage',
                'inventory.view',
                'inventory.adjust',
                'purchase.manage',
                'sales.pos',
                'sales.return',
                'counter.session.manage',
                'reports.view',
                'audit.view',
            ],
            'org_manager' => [
                'branches.manage',
                'users.manage',
                'units.manage',
                'suppliers.manage',
                'products.manage',
                'inventory.view',
                'purchase.manage',
                'sales.pos',
                'sales.return',
                'counter.session.manage',
                'reports.view',
            ],
            'branch_manager' => [
                'units.manage',
                'suppliers.manage',
                'products.manage',
                'inventory.view',
                'inventory.adjust',
                'purchase.manage',
                'sales.pos',
                'sales.return',
                'counter.session.manage',
                'reports.view',
            ],
            'pharmacist' => ['inventory.view', 'sales.pos', 'sales.return'],
            'cashier' => ['sales.pos', 'sales.return', 'counter.session.manage'],
            'storekeeper' => ['suppliers.manage', 'inventory.view', 'inventory.adjust', 'purchase.manage'],
            'accountant' => ['reports.view'],
            'inventory_auditor' => ['inventory.view', 'audit.view', 'reports.view'],
            'viewer' => ['reports.view'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(
                collect($rolePermissions)
                    ->map(fn (string $permission) => $permissionModels[$permission])
                    ->all()
            );
        }
    }
}
