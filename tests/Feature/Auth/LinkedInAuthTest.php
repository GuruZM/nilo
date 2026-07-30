<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

function mockLinkedInUser(string $id = 'linkedin-123', string $email = 'linkedin@example.com', string $name = 'LinkedIn User'): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('linkedin-openid')->andReturn($provider);
}

beforeEach(function () {
    config()->set('services.linkedin-openid.enabled', true);
});

test('linkedin routes are disabled when the provider is turned off', function () {
    config()->set('services.linkedin-openid.enabled', false);

    $this->get(route('linkedin.redirect'))->assertNotFound();
    $this->get(route('linkedin.callback'))->assertNotFound();
});

test('linkedin redirect endpoint redirects to linkedin', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('redirect')->andReturn(redirect('https://www.linkedin.com/oauth/v2/authorization'));
    Socialite::shouldReceive('driver')->with('linkedin-openid')->andReturn($provider);

    $response = $this->get(route('linkedin.redirect'));

    $response->assertRedirect('https://www.linkedin.com/oauth/v2/authorization');
});

test('new user registering with linkedin is created on the free plan and sent to the dashboard', function () {
    Plan::factory()->create(['slug' => 'free', 'price' => 0, 'is_active' => true]);

    mockLinkedInUser(id: 'linkedin-999', email: 'newperson@example.com', name: 'New Person');

    $response = $this->withSession(['oauth_intent' => 'register', 'oauth_terms' => true])->get(route('linkedin.callback'));

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');

    $user = User::where('email', 'newperson@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->linkedin_id)->toBe('linkedin-999');
    expect($user->password)->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->hasActiveSubscription())->toBeTrue();
});

test('signing in with linkedin for an unknown account does not register and redirects to the register page', function () {
    mockLinkedInUser(id: 'linkedin-404', email: 'stranger@example.com', name: 'Stranger');

    $response = $this->withSession(['oauth_intent' => 'login'])->get(route('linkedin.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('register'));
    $response->assertSessionHas('error');
    expect(User::where('email', 'stranger@example.com')->exists())->toBeFalse();
});

test('registering with linkedin without agreeing to the terms is rejected', function () {
    mockLinkedInUser(id: 'linkedin-406', email: 'noterms@example.com', name: 'No Terms');

    $response = $this->withSession(['oauth_intent' => 'register'])->get(route('linkedin.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('register'));
    $response->assertSessionHas('error');
    expect(User::where('email', 'noterms@example.com')->exists())->toBeFalse();
});

test('existing password account is auto-linked by email on linkedin sign in', function () {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'password' => Hash::make('secret-password'),
        'linkedin_id' => null,
    ]);

    mockLinkedInUser(id: 'linkedin-555', email: 'existing@example.com');

    $response = $this->get(route('linkedin.callback'));

    $this->assertAuthenticatedAs($user->fresh());
    $response->assertRedirect('/dashboard');

    expect($user->fresh()->linkedin_id)->toBe('linkedin-555');
    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
});

test('returning user matched by linkedin_id is logged in without creating a duplicate', function () {
    $user = User::factory()->create([
        'email' => 'returning@example.com',
        'linkedin_id' => 'linkedin-777',
    ]);

    mockLinkedInUser(id: 'linkedin-777', email: 'returning@example.com');

    $response = $this->get(route('linkedin.callback'));

    $this->assertAuthenticatedAs($user->fresh());
    $response->assertRedirect('/dashboard');
    expect(User::count())->toBe(1);
});

test('linkedin oauth failure redirects back to login with an error', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andThrow(new Exception('invalid state'));
    Socialite::shouldReceive('driver')->with('linkedin-openid')->andReturn($provider);

    $response = $this->get(route('linkedin.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
});

test('new user registering with linkedin flashes a welcome message for the toast', function () {
    Plan::factory()->create(['slug' => 'free', 'price' => 0, 'is_active' => true]);

    mockLinkedInUser(id: 'linkedin-889', email: 'toast@example.com', name: 'Toast Person');

    $response = $this->withSession(['oauth_intent' => 'register', 'oauth_terms' => true])->get(route('linkedin.callback'));

    $response->assertSessionHas('success', 'Welcome to '.config('app.name').'! Your account is ready.');
});

test('returning linkedin user does not see the welcome toast', function () {
    $user = User::factory()->create([
        'email' => 'returning-toast@example.com',
        'linkedin_id' => 'linkedin-322',
    ]);

    mockLinkedInUser(id: 'linkedin-322', email: 'returning-toast@example.com');

    $response = $this->get(route('linkedin.callback'));

    $response->assertSessionMissing('success');
});
