<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

/**
 * Stub the provider so that hitting the redirect route lands somewhere
 * assertable instead of reaching out to Google.
 */
function mockReauthRedirect(string $driver = 'google'): void
{
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('with')->andReturnSelf();
    $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    Socialite::shouldReceive('driver')->with($driver)->andReturn($provider);
}

/**
 * Stub the account that comes back from the provider on the callback leg.
 */
function mockReauthCallback(string $id, string $driver = 'google'): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getEmail')->andReturn('someone@example.com');
    $socialiteUser->shouldReceive('getName')->andReturn('Someone');

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with($driver)->andReturn($provider);
}

/**
 * The provider returns to its sign-in callback, so a confirmation only resumes
 * when the session says one is in flight. Tests that skip the redirect leg have
 * to plant that marker themselves.
 *
 * @param  array<string, mixed>  $session
 * @return array<string, mixed>
 */
function pendingConfirmation(array $session, string $driver = 'google'): array
{
    return array_merge(['confirm_identity.provider' => $driver], $session);
}

function confirmedAt(): ?int
{
    return session('auth.password_confirmed_at');
}

test('a password user confirms their identity with their password', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this->actingAs($user)
        ->from(route('two-factor.show'))
        ->post(route('confirm-identity.password'), ['password' => 'secret-password']);

    $response->assertSessionHasNoErrors()->assertRedirect(route('two-factor.show'));
    expect(confirmedAt())->not->toBeNull();
});

test('the wrong password does not confirm identity', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this->actingAs($user)
        ->from(route('two-factor.show'))
        ->post(route('confirm-identity.password'), ['password' => 'not-the-password']);

    $response->assertSessionHasErrors('password');
    expect(confirmedAt())->toBeNull();
});

test('a user without a password cannot confirm identity with the password route', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    $response = $this->actingAs($user)
        ->from(route('two-factor.show'))
        ->post(route('confirm-identity.password'), ['password' => 'anything']);

    $response->assertSessionHasErrors('password');
    expect(confirmedAt())->toBeNull();
});

test('the provider redirect stores the pending action and sends the user to the provider', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    mockReauthRedirect();

    $response = $this->actingAs($user)
        ->get(route('confirm-identity.redirect', ['provider' => 'google', 'intent' => 'enable']));

    $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    expect(session('confirm_identity.intent'))->toBe('enable');
});

test('an unrecognised pending action is not stored', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    mockReauthRedirect();

    $this->actingAs($user)
        ->get(route('confirm-identity.redirect', ['provider' => 'google', 'intent' => 'drop-database']));

    expect(session('confirm_identity.intent'))->toBeNull();
});

test('a user cannot re-authenticate against a provider they have not linked', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => Hash::make('secret-password'),
        'google_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('confirm-identity.redirect', ['provider' => 'google']))
        ->assertForbidden();
});

test('linkedin is not offered as a re-authentication provider', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'linkedin_id' => 'linkedin-1',
    ]);

    $this->actingAs($user)
        ->get(route('confirm-identity.redirect', ['provider' => 'linkedin']))
        ->assertNotFound();
});

test('coming back from the provider as the same account confirms identity and resumes the action', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    mockReauthCallback('google-1');

    $response = $this->actingAs($user)
        ->withSession(pendingConfirmation(['confirm_identity.intent' => 'enable']))
        ->get(route('google.callback'));

    $response->assertRedirect(route('two-factor.show'));
    $response->assertSessionHas('resumeAction', 'enable');
    expect(confirmedAt())->not->toBeNull();
});

test('coming back from the provider as a different account does not confirm identity', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    mockReauthCallback('google-somebody-else');

    $response = $this->actingAs($user)
        ->withSession(pendingConfirmation(['confirm_identity.intent' => 'enable']))
        ->get(route('google.callback'));

    $response->assertRedirect(route('two-factor.show'));
    $response->assertSessionHas('error');
    $response->assertSessionMissing('resumeAction');
    expect(confirmedAt())->toBeNull();
});

test('a failed provider handshake does not confirm identity', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andThrow(new Exception('invalid state'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->actingAs($user)
        ->withSession(pendingConfirmation([]))
        ->get(route('google.callback'));

    $response->assertRedirect(route('two-factor.show'));
    $response->assertSessionHas('error');
    expect(confirmedAt())->toBeNull();
});

test('the confirmation routes are closed to guests', function () {
    $this->post(route('confirm-identity.password'), ['password' => 'x'])
        ->assertRedirect(route('login'));

    $this->get(route('confirm-identity.redirect', ['provider' => 'google']))
        ->assertRedirect(route('login'));
});

test('the redirect leg records which page started the round trip', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    mockReauthRedirect();

    $this->actingAs($user)
        ->get(route('confirm-identity.redirect', [
            'provider' => 'google',
            'intent' => 'register-passkey',
            'return' => 'passkeys',
        ]));

    expect(session('confirm_identity.intent'))->toBe('register-passkey');
    expect(session('confirm_identity.return_to'))->toBe('passkeys');
});

test('an unrecognised return destination is not stored', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    mockReauthRedirect();

    $this->actingAs($user)
        ->get(route('confirm-identity.redirect', [
            'provider' => 'google',
            'return' => 'somewhere-else',
        ]));

    expect(session('confirm_identity.return_to'))->toBeNull();
});

test('the return leg goes back to the page that started the round trip', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    mockReauthCallback('google-1');

    $response = $this->actingAs($user)
        ->withSession(pendingConfirmation([
            'confirm_identity.intent' => 'register-passkey',
            'confirm_identity.return_to' => 'passkeys',
        ]))
        ->get(route('google.callback'));

    $response->assertRedirect(route('passkeys.show'));
    $response->assertSessionHas('resumeAction', 'register-passkey');
    expect(confirmedAt())->not->toBeNull();
});

test('the provider is sent the redirect uri it already has registered', function () {
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
        'services.google.redirect' => 'http://localhost:8000/auth/google/callback',
    ]);

    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    $response = $this->actingAs($user)->get(route('confirm-identity.redirect', [
        'provider' => 'google',
        'intent' => 'register-passkey',
        'return' => 'passkeys',
    ]));

    $query = [];
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query['redirect_uri'] ?? null)
        ->toBe('http://localhost:8000/auth/google/callback')
        ->and($query['prompt'] ?? null)->toBe('login');

    expect(session('confirm_identity.provider'))->toBe('google');
});

test('a signed in user with no confirmation in flight is turned away from the callback', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    $this->actingAs($user)
        ->get(route('google.callback'))
        ->assertRedirect(config('fortify.home'));

    expect(confirmedAt())->toBeNull();
});

/**
 * The sign-in branch would log whoever came back from the provider into the
 * session. Losing the `guest` guard on this route must not let that reach a
 * user who is already signed in.
 */
test('the callback cannot switch a signed in user into another account', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    $someoneElse = User::factory()->withSubscription()->create([
        'google_id' => 'google-2',
    ]);

    mockReauthCallback('google-2');

    $this->actingAs($user)
        ->get(route('google.callback'))
        ->assertRedirect(config('fortify.home'));

    expect(auth()->id())->toBe($user->id)
        ->and(auth()->id())->not->toBe($someoneElse->id);
});

test('the confirmation only resumes for the provider that started it', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
        'linkedin_id' => 'linkedin-1',
    ]);

    mockReauthCallback('google-1');

    $this->actingAs($user)
        ->withSession(pendingConfirmation(['confirm_identity.intent' => 'enable'], 'linkedin'))
        ->get(route('google.callback'))
        ->assertRedirect(config('fortify.home'));

    expect(confirmedAt())->toBeNull();
});

test('a round trip with no recorded origin lands on the two factor page', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    mockReauthCallback('google-1');

    $this->actingAs($user)
        ->withSession(pendingConfirmation(['confirm_identity.intent' => 'enable']))
        ->get(route('google.callback'))
        ->assertRedirect(route('two-factor.show'));
});
