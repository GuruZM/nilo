<?php

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds a verified user holding the super-admin role', function () {
    $this->seed(SuperAdminSeeder::class);

    $user = User::where('email', config('nilo.super_admin.email'))->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('super-admin'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check(config('nilo.super_admin.password'), $user->password))->toBeTrue();
});

it('is idempotent and does not duplicate the account or the role', function () {
    $this->seed(SuperAdminSeeder::class);
    $this->seed(SuperAdminSeeder::class);

    $email = config('nilo.super_admin.email');

    expect(User::where('email', $email)->count())->toBe(1);

    $user = User::where('email', $email)->first();
    expect($user->roles()->where('name', 'super-admin')->count())->toBe(1);
});

it('reaches the admin dashboard without an active subscription', function () {
    $this->seed(SuperAdminSeeder::class);

    $user = User::where('email', config('nilo.super_admin.email'))->first();

    expect($user->hasActiveSubscription())->toBeFalse();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertSuccessful();
});

it('lands on the admin dashboard instead of the plans page after login', function () {
    $this->seed(SuperAdminSeeder::class);

    $user = User::where('email', config('nilo.super_admin.email'))->first();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

it('still sends unsubscribed regular users to the plans page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('subscription.select'));
});

it('does not divert an admin who holds an active subscription', function () {
    $this->seed(SuperAdminSeeder::class);

    $user = User::factory()->withSubscription()->create(['email_verified_at' => now()]);
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful();
});

it('forbids the admin area to users without the role', function () {
    $this->seed(SuperAdminSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});
