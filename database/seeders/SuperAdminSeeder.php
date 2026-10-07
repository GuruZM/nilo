<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
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

        if (app()->isProduction()) {
            $this->guardProductionCredentials($email, $password);
        }

        $user = User::firstOrNew(['email' => $email]);

        $user->fill([
            'name' => config('nilo.super_admin.name'),
            'password' => Hash::make($password),
        ]);
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        $user->assignRole('super-admin');

        $this->command?->info("Super admin ready: {$email}");
    }

    /**
     * Refuse to mint a guessable super admin on a live server.
     *
     * The config falls back to admin@nilo.test / "password" so local setups
     * work with no .env changes; in production that fallback would be an open
     * door to /admin, so a missing or weak value stops the seed instead.
     */
    private function guardProductionCredentials(?string $email, ?string $password): void
    {
        if (blank($email) || $email === 'admin@nilo.test') {
            throw new RuntimeException('Set NILO_SUPER_ADMIN_EMAIL in .env before seeding the super admin in production.');
        }

        if (blank($password) || $password === 'password' || strlen($password) < 12) {
            throw new RuntimeException('Set NILO_SUPER_ADMIN_PASSWORD in .env to at least 12 characters (not "password") before seeding the super admin in production.');
        }
    }
}
