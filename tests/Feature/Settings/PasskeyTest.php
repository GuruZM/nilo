<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function () {
    if (! Features::canManagePasskeys()) {
        $this->markTestSkipped('Passkeys are not enabled.');
    }
});

/**
 * @param  array<string, mixed>  $attributes
 */
function passkeyFor(User $user, array $attributes = []): Passkey
{
    $passkey = new Passkey(array_merge([
        'name' => 'MacBook Pro',
        'credential_id' => 'credential-'.uniqid(),
        'credential' => ['type' => 'public-key'],
    ], $attributes));

    $passkey->user_id = $user->id;
    $passkey->save();

    return $passkey;
}

test('the passkeys settings page lists the users passkeys', function () {
    $user = User::factory()->withSubscription()->create();
    passkeyFor($user, ['name' => 'Work laptop']);

    $this->actingAs($user)
        ->get(route('passkeys.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/passkeys')
            ->has('passkeys', 1)
            ->where('passkeys.0.name', 'Work laptop')
        );
});

test('the passkeys page does not list another users passkeys', function () {
    $user = User::factory()->withSubscription()->create();
    passkeyFor(User::factory()->create(), ['name' => 'Someone elses key']);

    $this->actingAs($user)
        ->get(route('passkeys.show'))
        ->assertInertia(fn (Assert $page) => $page->has('passkeys', 0));
});

test('the passkeys page renders unconfirmed rather than bouncing to a login-looking screen', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->get(route('passkeys.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/passkeys')
            ->where('identityConfirmed', false)
            ->where('hasPassword', true)
        );
});

test('guests cannot reach the passkeys settings page', function () {
    $this->get(route('passkeys.show'))->assertRedirect(route('login'));
});

test('registration options require a confirmed session', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->getJson(route('passkey.registration-options'))
        ->assertStatus(423);
});

test('a confirmed session can fetch registration options', function () {
    $user = User::factory()->withSubscription()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->getJson(route('passkey.registration-options'));

    $response->assertOk()->assertJsonStructure(['options' => ['challenge', 'rp', 'user']]);
});

test('a user can delete their own passkey', function () {
    $user = User::factory()->withSubscription()->create();
    $passkey = passkeyFor($user);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('passkey.destroy', $passkey));

    expect(Passkey::find($passkey->id))->toBeNull();
});

test('a user cannot delete someone elses passkey', function () {
    $user = User::factory()->withSubscription()->create();
    $passkey = passkeyFor(User::factory()->create());

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('passkey.destroy', $passkey))
        ->assertForbidden();

    expect(Passkey::find($passkey->id))->not->toBeNull();
});

test('the passkeys page hands back the action to resume after confirmation', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->withSession([
            'auth.password_confirmed_at' => time(),
            'resumeAction' => 'register-passkey',
        ])
        ->get(route('passkeys.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('identityConfirmed', true)
            ->where('resumeAction', 'register-passkey')
        );
});

test('a google only user comes back from re-authentication ready to add a passkey', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('google-1');

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->actingAs($user)
        ->withSession([
            'confirm_identity.provider' => 'google',
            'confirm_identity.intent' => 'register-passkey',
            'confirm_identity.return_to' => 'passkeys',
        ])
        ->get(route('google.callback'))
        ->assertRedirect(route('passkeys.show'));

    $this->actingAs($user)
        ->get(route('passkeys.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/passkeys')
            ->where('identityConfirmed', true)
            ->where('resumeAction', 'register-passkey')
            ->where('hasPassword', false)
            ->where('reauthProviders', ['google'])
        );
});

test('deleting a passkey requires a confirmed session', function () {
    $user = User::factory()->withSubscription()->create();
    $passkey = passkeyFor($user);

    $this->actingAs($user)
        ->deleteJson(route('passkey.destroy', $passkey))
        ->assertStatus(423);

    expect(Passkey::find($passkey->id))->not->toBeNull();
});
