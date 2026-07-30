<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

function mockFacebookUser(string $id = 'facebook-123', string $email = 'facebook@example.com', string $name = 'Facebook User'): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('facebook')->andReturn($provider);
}

beforeEach(function () {
    config()->set('services.facebook.enabled', true);
});

test('facebook routes are disabled when the provider is turned off', function () {
    config()->set('services.facebook.enabled', false);

    $this->get(route('facebook.redirect'))->assertNotFound();
    $this->get(route('facebook.callback'))->assertNotFound();
});

test('facebook redirect endpoint redirects to facebook', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('redirect')->andReturn(redirect('https://www.facebook.com/dialog/oauth'));
    Socialite::shouldReceive('driver')->with('facebook')->andReturn($provider);

    $response = $this->get(route('facebook.redirect'));

    $response->assertRedirect('https://www.facebook.com/dialog/oauth');
});

test('new user registering with facebook is created on the free plan and sent to the dashboard', function () {
    Plan::factory()->create(['slug' => 'free', 'price' => 0, 'is_active' => true]);

    mockFacebookUser(id: 'facebook-999', email: 'newperson@example.com', name: 'New Person');

    $response = $this->withSession(['oauth_intent' => 'register', 'oauth_terms' => true])->get(route('facebook.callback'));

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');

    $user = User::where('email', 'newperson@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->facebook_id)->toBe('facebook-999');
    expect($user->password)->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->hasActiveSubscription())->toBeTrue();
});

test('signing in with facebook for an unknown account does not register and redirects to the register page', function () {
    mockFacebookUser(id: 'facebook-404', email: 'stranger@example.com', name: 'Stranger');

    $response = $this->withSession(['oauth_intent' => 'login'])->get(route('facebook.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('register'));
    $response->assertSessionHas('error');
    expect(User::where('email', 'stranger@example.com')->exists())->toBeFalse();
});

test('registering with facebook without agreeing to the terms is rejected', function () {
    mockFacebookUser(id: 'facebook-406', email: 'noterms@example.com', name: 'No Terms');

    $response = $this->withSession(['oauth_intent' => 'register'])->get(route('facebook.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('register'));
    $response->assertSessionHas('error');
    expect(User::where('email', 'noterms@example.com')->exists())->toBeFalse();
});

test('existing password account is auto-linked by email on facebook sign in', function () {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'password' => Hash::make('secret-password'),
        'facebook_id' => null,
    ]);

    mockFacebookUser(id: 'facebook-555', email: 'existing@example.com');

    $response = $this->get(route('facebook.callback'));

    $this->assertAuthenticatedAs($user->fresh());
    $response->assertRedirect('/dashboard');

    expect($user->fresh()->facebook_id)->toBe('facebook-555');
    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
});

test('returning user matched by facebook_id is logged in without creating a duplicate', function () {
    $user = User::factory()->create([
        'email' => 'returning@example.com',
        'facebook_id' => 'facebook-777',
    ]);

    mockFacebookUser(id: 'facebook-777', email: 'returning@example.com');

    $response = $this->get(route('facebook.callback'));

    $this->assertAuthenticatedAs($user->fresh());
    $response->assertRedirect('/dashboard');
    expect(User::count())->toBe(1);
});

test('facebook oauth failure redirects back to login with an error', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andThrow(new Exception('invalid state'));
    Socialite::shouldReceive('driver')->with('facebook')->andReturn($provider);

    $response = $this->get(route('facebook.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
});

test('new user registering with facebook flashes a welcome message for the toast', function () {
    Plan::factory()->create(['slug' => 'free', 'price' => 0, 'is_active' => true]);

    mockFacebookUser(id: 'facebook-889', email: 'toast@example.com', name: 'Toast Person');

    $response = $this->withSession(['oauth_intent' => 'register', 'oauth_terms' => true])->get(route('facebook.callback'));

    $response->assertSessionHas('success', 'Welcome to '.config('app.name').'! Your account is ready.');
});

test('returning facebook user does not see the welcome toast', function () {
    $user = User::factory()->create([
        'email' => 'returning-toast@example.com',
        'facebook_id' => 'facebook-322',
    ]);

    mockFacebookUser(id: 'facebook-322', email: 'returning-toast@example.com');

    $response = $this->get(route('facebook.callback'));

    $response->assertSessionMissing('success');
});
