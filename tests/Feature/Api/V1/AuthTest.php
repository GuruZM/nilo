<?php

use App\Models\Plan;
use App\Models\User;
use App\Support\TwoFactorChallenge;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;

/* ---------------------------------------------------------------- Login -- */

it('exchanges valid credentials for a bearer token', function () {
    $user = User::factory()->create(['password' => 'password']);

    $response = $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['token', 'user' => ['id', 'email', 'email_verified']])
        ->assertJsonPath('user.id', $user->id);

    expect(PersonalAccessToken::where('tokenable_id', $user->id)->value('name'))->toBe('Pixel 8');
});

it('names the token for the device or falls back to a generic label', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSuccessful();

    expect(PersonalAccessToken::where('tokenable_id', $user->id)->value('name'))->toBe('mobile');
});

it('refuses a wrong password without issuing a token', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    expect(PersonalAccessToken::count())->toBe(0);
});

/**
 * The API shares the web login's throttle key, so tokens cannot be brute
 * forced faster through the phone than through the browser.
 */
it('throttles login on the same key the web form uses', function () {
    $user = User::factory()->create(['password' => 'password']);
    $key = str($user->email)->lower()->append('|127.0.0.1')->transliterate()->value();

    RateLimiter::clear($key);

    foreach (range(1, 5) as $ignored) {
        RateLimiter::hit($key);
    }

    $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    expect(PersonalAccessToken::count())->toBe(0);
});

/* ------------------------------------------------------ Two factor auth -- */

it('stops at a two factor challenge instead of issuing a token', function () {
    if (! Features::enabled(Features::twoFactorAuthentication())) {
        $this->markTestSkipped('Two factor authentication is not enabled.');
    }

    $user = twoFactorUser();

    $response = $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('two_factor', true)
        ->assertJsonStructure(['challenge'])
        ->assertJsonMissingPath('token');

    expect(PersonalAccessToken::count())->toBe(0);
});

it('redeems a two factor challenge and a valid code for a token', function () {
    if (! Features::enabled(Features::twoFactorAuthentication())) {
        $this->markTestSkipped('Two factor authentication is not enabled.');
    }

    $user = twoFactorUser();

    $this->postJson(route('api.v1.auth.two-factor'), [
        'challenge' => TwoFactorChallenge::issue($user),
        'code' => validTwoFactorCode($user),
        'device_name' => 'iPhone 15',
    ])->assertSuccessful()->assertJsonPath('user.id', $user->id);

    expect(PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(1);
});

it('refuses an invalid two factor code', function () {
    if (! Features::enabled(Features::twoFactorAuthentication())) {
        $this->markTestSkipped('Two factor authentication is not enabled.');
    }

    $user = twoFactorUser();

    $this->postJson(route('api.v1.auth.two-factor'), [
        'challenge' => TwoFactorChallenge::issue($user),
        'code' => '000000',
    ])->assertStatus(422)->assertJsonValidationErrors('code');

    expect(PersonalAccessToken::count())->toBe(0);
});

it('spends a recovery code once and replaces it', function () {
    if (! Features::enabled(Features::twoFactorAuthentication())) {
        $this->markTestSkipped('Two factor authentication is not enabled.');
    }

    $user = twoFactorUser();
    $recoveryCode = $user->recoveryCodes()[0];

    $this->postJson(route('api.v1.auth.two-factor'), [
        'challenge' => TwoFactorChallenge::issue($user),
        'recovery_code' => $recoveryCode,
    ])->assertSuccessful();

    expect($user->fresh()->recoveryCodes())->not->toContain($recoveryCode);

    /** The same code a second time is worthless. */
    $this->postJson(route('api.v1.auth.two-factor'), [
        'challenge' => TwoFactorChallenge::issue($user),
        'recovery_code' => $recoveryCode,
    ])->assertStatus(422)->assertJsonValidationErrors('recovery_code');
});

/**
 * The challenge stands in for a session key, so it has to expire like one.
 */
it('refuses an expired challenge', function () {
    $user = User::factory()->create();

    $expired = Crypt::encryptString(json_encode([
        'id' => $user->id,
        'exp' => now()->subMinute()->timestamp,
    ]));

    $this->postJson(route('api.v1.auth.two-factor'), [
        'challenge' => $expired,
        'code' => '123456',
    ])->assertStatus(422)->assertJsonValidationErrors('challenge');
});

it('refuses a tampered challenge', function () {
    $this->postJson(route('api.v1.auth.two-factor'), [
        'challenge' => 'not-a-real-challenge',
        'code' => '123456',
    ])->assertStatus(422)->assertJsonValidationErrors('challenge');
});

it('requires either a code or a recovery code', function () {
    $user = User::factory()->create();

    $this->postJson(route('api.v1.auth.two-factor'), [
        'challenge' => TwoFactorChallenge::issue($user),
    ])->assertStatus(422)->assertJsonValidationErrors('code');
});

/* ------------------------------------------------------------- Register -- */

it('registers an account on the free plan and returns a token', function () {
    Plan::factory()->create(['slug' => 'free', 'is_active' => true]);

    $response = $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Mwansa Banda',
        'email' => 'mwansa@example.com',
        'password' => 'Password!2345',
        'password_confirmation' => 'Password!2345',
        'terms' => true,
        'device_name' => 'Pixel 8',
    ]);

    $response->assertCreated()->assertJsonStructure(['token', 'user']);

    $user = User::where('email', 'mwansa@example.com')->firstOrFail();

    expect($user->activePlan()?->slug)->toBe('free');
});

it('refuses to register without agreeing to the terms', function () {
    $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Mwansa Banda',
        'email' => 'mwansa@example.com',
        'password' => 'Password!2345',
        'password_confirmation' => 'Password!2345',
    ])->assertStatus(422)->assertJsonValidationErrors('terms');

    expect(User::where('email', 'mwansa@example.com')->exists())->toBeFalse();
});

it('refuses to register an email that already has an account', function () {
    $existing = User::factory()->create();

    $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Mwansa Banda',
        'email' => $existing->email,
        'password' => 'Password!2345',
        'password_confirmation' => 'Password!2345',
        'terms' => true,
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

/* --------------------------------------------------------------- Logout -- */

it('revokes only the token used to sign out', function () {
    $user = User::factory()->create();
    $phone = $user->createToken('Pixel 8')->plainTextToken;
    $user->createToken('iPad')->plainTextToken;

    $this->withToken($phone)
        ->postJson(route('api.v1.auth.logout'))
        ->assertSuccessful();

    expect(PersonalAccessToken::where('tokenable_id', $user->id)->pluck('name')->all())
        ->toBe(['iPad']);
});

it('revokes every token when signing out of all devices', function () {
    $user = User::factory()->create();
    $phone = $user->createToken('Pixel 8')->plainTextToken;
    $user->createToken('iPad');

    $this->withToken($phone)
        ->deleteJson(route('api.v1.auth.tokens.destroy'))
        ->assertSuccessful();

    expect(PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(0);
});

it('refuses a revoked token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Pixel 8')->plainTextToken;

    $this->withToken($token)->postJson(route('api.v1.auth.logout'))->assertSuccessful();

    betweenRequests();

    $this->withToken($token)->getJson('/api/v1/ping')->assertUnauthorized();
});

/* ------------------------------------------------- Password and verify -- */

it('answers a password reset request the same way whether or not the account exists', function () {
    $user = User::factory()->create();

    $known = $this->postJson(route('api.v1.auth.forgot-password'), ['email' => $user->email]);
    $unknown = $this->postJson(route('api.v1.auth.forgot-password'), ['email' => 'nobody@example.com']);

    $known->assertSuccessful();
    $unknown->assertSuccessful();

    expect($known->json('message'))->toBe($unknown->json('message'));
});

it('resends the verification email only while the address is unverified', function () {
    $unverified = User::factory()->unverified()->create();
    $verified = User::factory()->create();

    $this->withToken($unverified->createToken('Pixel 8')->plainTextToken)
        ->postJson(route('api.v1.auth.verification.send'))
        ->assertSuccessful()
        ->assertJsonPath('message', 'Verification link sent.');

    betweenRequests();

    $this->withToken($verified->createToken('Pixel 8')->plainTextToken)
        ->postJson(route('api.v1.auth.verification.send'))
        ->assertSuccessful()
        ->assertJsonPath('message', 'Your email is already verified.');
});

/* --------------------------------------------------------------- Helpers -- */

function twoFactorUser(): User
{
    $user = User::factory()->create(['password' => 'password']);

    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt(
            app(Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider::class)->generateSecretKey()
        ),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(
            json_encode(collect()->times(8, fn () => Laravel\Fortify\RecoveryCode::generate())->all())
        ),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user->fresh();
}

function validTwoFactorCode(User $user): string
{
    return app(PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp(
        Fortify::currentEncrypter()->decrypt($user->two_factor_secret)
    );
}

/**
 * Every real API call arrives at a fresh application, so a token resolved on
 * one request is never carried into the next. The test client keeps one
 * application for the whole test, which would quietly paper over a token that
 * should no longer work — this puts the guard back to how a new request finds
 * it.
 */
function betweenRequests(): void
{
    app('auth')->forgetGuards();
}
