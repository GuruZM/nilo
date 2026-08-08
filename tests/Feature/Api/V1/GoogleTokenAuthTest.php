<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    config()->set('services.google.client_ids', ['web.apps.googleusercontent.com', 'android.apps.googleusercontent.com']);
    config()->set('services.google.tokeninfo_url', 'https://oauth2.googleapis.com/tokeninfo');
});

/* ------------------------------------------------------------- Linking -- */

it('signs in a returning user matched by their google id', function () {
    fakeTokenInfo(['sub' => '1234', 'email' => 'someone.else@example.com']);

    $user = User::factory()->create(['google_id' => '1234']);

    $this->postJson(route('api.v1.auth.google'), [
        'id_token' => 'valid-token',
        'device_name' => 'Pixel 8',
    ])->assertSuccessful()->assertJsonPath('user.id', $user->id);

    expect(PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(1);
});

/**
 * Someone who signed up with a password and later taps "Continue with Google"
 * must land back in their own account, not a duplicate.
 */
it('links an existing password account by email and backfills the google id', function () {
    fakeTokenInfo(['sub' => '1234', 'email' => 'mwansa@example.com']);

    $user = User::factory()->create(['email' => 'mwansa@example.com', 'google_id' => null]);

    $this->postJson(route('api.v1.auth.google'), ['id_token' => 'valid-token'])
        ->assertSuccessful()
        ->assertJsonPath('user.id', $user->id);

    expect($user->fresh()->google_id)->toBe('1234')
        ->and(User::count())->toBe(1);
});

it('creates a new account on the free plan when the intent is to register', function () {
    Plan::factory()->create(['slug' => 'free', 'is_active' => true]);

    fakeTokenInfo(['sub' => '1234', 'email' => 'mwansa@example.com', 'name' => 'Mwansa Banda']);

    $this->postJson(route('api.v1.auth.google'), [
        'id_token' => 'valid-token',
        'intent' => 'register',
        'terms' => true,
    ])->assertSuccessful();

    $user = User::where('email', 'mwansa@example.com')->firstOrFail();

    expect($user->name)->toBe('Mwansa Banda')
        ->and($user->google_id)->toBe('1234')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->activePlan()?->slug)->toBe('free');
});

it('refuses to create an account when the intent is only to sign in', function () {
    fakeTokenInfo(['sub' => '1234', 'email' => 'nobody@example.com']);

    $this->postJson(route('api.v1.auth.google'), ['id_token' => 'valid-token'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_token');

    expect(User::count())->toBe(0);
});

it('refuses to register without agreeing to the terms', function () {
    fakeTokenInfo(['sub' => '1234', 'email' => 'mwansa@example.com']);

    $this->postJson(route('api.v1.auth.google'), [
        'id_token' => 'valid-token',
        'intent' => 'register',
    ])->assertStatus(422)->assertJsonValidationErrors('terms');

    expect(User::count())->toBe(0);
});

/* -------------------------------------------------------- Verification -- */

/**
 * Without the audience check, an ID token minted for any other Google app
 * would be accepted here — which is a complete authentication bypass, because
 * the attacker controls that app and so controls the `sub` and `email` claims.
 */
it('refuses a token minted for another application', function () {
    fakeTokenInfo(['sub' => '1234', 'email' => 'mwansa@example.com', 'aud' => 'someone-elses-app.apps.googleusercontent.com']);

    User::factory()->create(['google_id' => '1234']);

    $this->postJson(route('api.v1.auth.google'), ['id_token' => 'valid-token'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_token');

    expect(PersonalAccessToken::count())->toBe(0);
});

it('accepts a token minted for the android client', function () {
    fakeTokenInfo(['sub' => '1234', 'email' => 'mwansa@example.com', 'aud' => 'android.apps.googleusercontent.com']);

    $user = User::factory()->create(['google_id' => '1234']);

    $this->postJson(route('api.v1.auth.google'), ['id_token' => 'valid-token'])
        ->assertSuccessful()
        ->assertJsonPath('user.id', $user->id);
});

it('refuses a token from an unexpected issuer', function () {
    fakeTokenInfo(['sub' => '1234', 'email' => 'mwansa@example.com', 'iss' => 'https://evil.example.com']);

    User::factory()->create(['google_id' => '1234']);

    $this->postJson(route('api.v1.auth.google'), ['id_token' => 'valid-token'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_token');
});

it('refuses an expired token', function () {
    fakeTokenInfo(['sub' => '1234', 'email' => 'mwansa@example.com', 'exp' => now()->subHour()->timestamp]);

    User::factory()->create(['google_id' => '1234']);

    $this->postJson(route('api.v1.auth.google'), ['id_token' => 'valid-token'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_token');
});

/**
 * An unverified Google address must never be trusted, because
 * GoogleAccountLinker matches on email — trusting it would let anyone who can
 * name an address take over the account behind it.
 */
it('refuses a token whose email google has not verified', function () {
    fakeTokenInfo(['sub' => '1234', 'email' => 'mwansa@example.com', 'email_verified' => 'false']);

    User::factory()->create(['email' => 'mwansa@example.com']);

    $this->postJson(route('api.v1.auth.google'), ['id_token' => 'valid-token'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_token');
});

it('refuses a token google itself rejects', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_token'], 400)]);

    $this->postJson(route('api.v1.auth.google'), ['id_token' => 'nonsense'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_token');
});

/**
 * Verification fails closed: with nothing on the allow-list every token is
 * refused, rather than every token being accepted.
 */
it('refuses every token when no client ids are configured', function () {
    config()->set('services.google.client_ids', []);

    fakeTokenInfo(['sub' => '1234', 'email' => 'mwansa@example.com']);

    User::factory()->create(['google_id' => '1234']);

    $this->postJson(route('api.v1.auth.google'), ['id_token' => 'valid-token'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_token');
});

it('refuses to reach google without an id token', function () {
    Http::fake();

    $this->postJson(route('api.v1.auth.google'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_token');

    Http::assertNothingSent();
});

/* -------------------------------------------------------------- Helpers -- */

/**
 * @param  array<string, mixed>  $claims
 */
function fakeTokenInfo(array $claims = []): void
{
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response([
            'aud' => 'web.apps.googleusercontent.com',
            'iss' => 'https://accounts.google.com',
            'email_verified' => 'true',
            'exp' => now()->addHour()->timestamp,
            ...$claims,
        ]),
    ]);
}
