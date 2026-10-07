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

it('refuses to seed guessable credentials in production', function (?string $email, ?string $password) {
    $this->app->detectEnvironment(fn (): string => 'production');

    config([
        'nilo.super_admin.email' => $email,
        'nilo.super_admin.password' => $password,
    ]);

    expect(fn () => app(SuperAdminSeeder::class)->run())->toThrow(RuntimeException::class);

    expect(User::query()->whereHas('roles', fn ($query) => $query->where('name', 'super-admin'))->exists())->toBeFalse();
})->with([
    'default email' => ['admin@nilo.test', 'a-long-strong-passphrase'],
    'missing email' => [null, 'a-long-strong-passphrase'],
    'default password' => ['owner@resonantt.com', 'password'],
    'missing password' => ['owner@resonantt.com', null],
    'short password' => ['owner@resonantt.com', 'short-pass'],
]);

it('seeds the super admin in production when the credentials are set properly', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    config([
        'nilo.super_admin.email' => 'owner@resonantt.com',
        'nilo.super_admin.password' => 'a-long-strong-passphrase',
    ]);

    app(SuperAdminSeeder::class)->run();

    $user = User::where('email', 'owner@resonantt.com')->first();

    expect($user->hasRole('super-admin'))->toBeTrue()
        ->and(Hash::check('a-long-strong-passphrase', $user->password))->toBeTrue();
});

it('promotes an existing account with that email instead of duplicating it', function () {
    $existing = User::factory()->create(['email' => config('nilo.super_admin.email')]);

    $this->seed(SuperAdminSeeder::class);

    expect(User::where('email', $existing->email)->count())->toBe(1)
        ->and($existing->fresh()->hasRole('super-admin'))->toBeTrue();
});

it('does not create the demo super admin when the full seeder runs in production', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    config([
        'nilo.super_admin.email' => 'owner@resonantt.com',
        'nilo.super_admin.password' => 'a-long-strong-passphrase',
    ]);

    app(\Database\Seeders\DatabaseSeeder::class)->run();

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse()
        ->and(User::where('email', 'owner@resonantt.com')->first()->hasRole('super-admin'))->toBeTrue();
});
