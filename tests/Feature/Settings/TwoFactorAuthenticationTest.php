<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('two factor settings page can be rendered', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/two-factor')
            ->where('twoFactorEnabled', false)
        );
});

test('the settings page renders unconfirmed instead of bouncing to a login-looking screen', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->withSubscription()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $this->actingAs($user)
        ->get(route('two-factor.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/two-factor')
            ->where('identityConfirmed', false)
        );
});

test('the sensitive two factor actions stay gated even though the page is open', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->withSubscription()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $this->actingAs($user)
        ->post('/user/two-factor-authentication')
        ->assertRedirect(route('password.confirm'));
});

test('a confirmation older than the password timeout no longer counts', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time() - config('auth.password_timeout') - 60])
        ->get(route('two-factor.show'))
        ->assertInertia(fn (Assert $page) => $page->where('identityConfirmed', false));
});

test('the settings page reports which providers can re-authenticate the user', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
        'linkedin_id' => 'linkedin-1',
    ]);

    $this->actingAs($user)
        ->get(route('two-factor.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasPassword', false)
            ->where('reauthProviders', ['google'])
        );
});

test('the pending action survives the round trip to the provider', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time(), 'resumeAction' => 'enable'])
        ->get(route('two-factor.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('identityConfirmed', true)
            ->where('resumeAction', 'enable')
        );
});

test('two factor settings page does not requires password confirmation when disabled', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->withSubscription()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => false,
    ]);

    $this->actingAs($user)
        ->get(route('two-factor.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/two-factor')
        );
});

test('two factor settings page returns forbidden response when two factor is disabled', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    config(['fortify.features' => []]);

    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.show'))
        ->assertForbidden();
});
