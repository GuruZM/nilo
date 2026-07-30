<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Provision the internal Nilo staff account that can reach /admin
     * without holding a subscription.
     */
    public function run(): void
    {
        if (! Schema::hasTable('roles')) {
            $this->command?->warn('Permission tables are missing - run migrations before seeding the super admin.');

            return;
        }

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $email = config('nilo.super_admin.email');
        $password = config('nilo.super_admin.password');

        $user = User::firstOrNew(['email' => $email]);

        $user->fill([
            'name' => config('nilo.super_admin.name'),
            'password' => Hash::make($password),
        ]);
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        $user->assignRole('super-admin');

        $this->command?->info("Super admin ready: {$email} / {$password}");
    }
}
