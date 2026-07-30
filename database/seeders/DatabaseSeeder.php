<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
        ]);

        // Only run RolePermissionSeeder if Spatie tables exist
        if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
            $this->call([
                RolePermissionSeeder::class,
                SuperAdminSeeder::class,
            ]);
        }

        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign super-admin role to demo user
        if ($user && \Illuminate\Support\Facades\Schema::hasTable('roles')) {
            $user->assignRole('super-admin');

            // Give the admin user a premium subscription
            if (! $user->subscription) {
                $premiumPlan = Plan::where('slug', 'premium')->first();
                if ($premiumPlan) {
                    Subscription::firstOrCreate(
                        ['user_id' => $user->id],
                        [
                            'plan_id' => $premiumPlan->id,
                            'status' => 'active',
                            'starts_at' => now(),
                            'payment_method' => 'admin_assigned',
                        ],
                    );
                }
            }
        }
    }
}
