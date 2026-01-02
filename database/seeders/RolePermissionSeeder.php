<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'super-admin',
            'company-admin',
            'staff',
            'viewer',
        ];

        $permissions = [
            'manage companies',
            'manage clients',
            'manage invoices',
            'manage quotations',
            'view dashboard',
        ];

        foreach ($roles as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Optionally assign all permissions to super-admin
        $superAdmin = \Spatie\Permission\Models\Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions($permissions);
        }
    }
}
