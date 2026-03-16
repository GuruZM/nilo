<?php

use App\Models\User;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Cookie;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('canResetPassword', true)
        );
});

test('login screen can be rendered when currencies are unavailable', function () {
    Schema::dropIfExists('currencies');

    $response = $this->get(route('login'));

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(implode('|', [$user->email, '127.0.0.1']), amount: 10);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');

    $errors = session('errors');

    $this->assertStringContainsString('Too many login attempts', $errors->first('email'));
});

test('blank session domain values do not produce invalid cookie domains', function () {
    $previousPutenv = getenv('SESSION_DOMAIN');
    $hadEnv = array_key_exists('SESSION_DOMAIN', $_ENV);
    $previousEnv = $_ENV['SESSION_DOMAIN'] ?? null;
    $hadServer = array_key_exists('SESSION_DOMAIN', $_SERVER);
    $previousServer = $_SERVER['SESSION_DOMAIN'] ?? null;

    putenv('SESSION_DOMAIN=');
    $_ENV['SESSION_DOMAIN'] = '';
    $_SERVER['SESSION_DOMAIN'] = '';
    Env::enablePutenv();

    try {
        $sessionConfig = require base_path('config/session.php');
    } finally {
        if ($previousPutenv === false) {
            putenv('SESSION_DOMAIN');
        } else {
            putenv("SESSION_DOMAIN={$previousPutenv}");
        }

        if ($hadEnv) {
            $_ENV['SESSION_DOMAIN'] = $previousEnv;
        } else {
            unset($_ENV['SESSION_DOMAIN']);
        }

        if ($hadServer) {
            $_SERVER['SESSION_DOMAIN'] = $previousServer;
        } else {
            unset($_SERVER['SESSION_DOMAIN']);
        }

        Env::enablePutenv();
    }

    $cookie = new Cookie('laravel_session', 'value', 0, '/', $sessionConfig['domain']);

    expect($sessionConfig['domain'])->toBeNull()
        ->and((string) $cookie)->not->toContain('; domain=');
});
