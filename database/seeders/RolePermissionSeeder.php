<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed roles (admin/marketing/sale) và permissions cho CRM.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'customers.create',
            'customers.import',
            'customers.view.own',
            'customers.view.assigned',
            'customers.view.all',
            'customers.update',
            'customers.reassign',
            'appointments.manage',
            'users.manage',
            'services.manage',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $marketing = Role::firstOrCreate(['name' => 'marketing', 'guard_name' => 'web']);
        $sale = Role::firstOrCreate(['name' => 'sale', 'guard_name' => 'web']);

        $admin->syncPermissions(Permission::all());

        $marketing->syncPermissions([
            'customers.create',
            'customers.import',
            'customers.view.own',
            'customers.update',
        ]);

        $sale->syncPermissions([
            'customers.view.assigned',
            'appointments.manage',
        ]);
    }
}
