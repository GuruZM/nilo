<?php

use App\Models\Plan;
use App\Models\User;
use App\Notifications\WelcomeEmail;
use App\Notifications\WelcomeVerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;

function mockGoogleUser(string $id = 'google-123', ?string $email = 'google@example.com', string $name = 'Google User', bool $emailVerified = true): void
{
    $socialiteUser = (new SocialiteTwoUser)
        ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $emailVerified])
        ->map(['id' => $id, 'email' => $email, 'name' => $name]);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('google redirect endpoint redirects to google', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('google.redirect'));

    $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

test('new user registering with google is created on the free plan and sent to the dashboard', function () {
    Plan::factory()->create(['slug' => 'free', 'price' => 0, 'is_active' => true]);

    mockGoogleUser(id: 'google-999', email: 'newperson@example.com', name: 'New Person');

    $response = $this->withSession(['oauth_intent' => 'register', 'oauth_terms' => true])->get(route('google.callback'));

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');

    $user = User::where('email', 'newperson@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->google_id)->toBe('google-999');
    expect($user->password)->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->hasActiveSubscription())->toBeTrue();
});

test('signing in with google for an unknown account does not register and redirects to the register page', function () {
    mockGoogleUser(id: 'google-404', email: 'stranger@example.com', name: 'Stranger');

    $response = $this->withSession(['oauth_intent' => 'login'])->get(route('google.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('register'));
    $response->assertSessionHas('error');
    expect(User::where('email', 'stranger@example.com')->exists())->toBeFalse();
});

test('signing in with google with no intent defaults to login and does not register', function () {
    mockGoogleUser(id: 'google-405', email: 'noneintent@example.com');

    $response = $this->get(route('google.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('register'));
    expect(User::where('email', 'noneintent@example.com')->exists())->toBeFalse();
});

test('the redirect endpoint stores the register intent and terms consent in the session', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('google.redirect', ['intent' => 'register', 'terms' => '1']));

    expect(session('oauth_intent'))->toBe('register');
    expect(session('oauth_terms'))->toBeTrue();
});

test('registering with google without agreeing to the terms is rejected', function () {
    mockGoogleUser(id: 'google-406', email: 'noterms@example.com', name: 'No Terms');

    $response = $this->withSession(['oauth_intent' => 'register'])->get(route('google.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('register'));
    $response->assertSessionHas('error');
    expect(User::where('email', 'noterms@example.com')->exists())->toBeFalse();
});

test('new google user receives the welcome email but not the verification email', function () {
    Notification::fake();
    Plan::factory()->create(['slug' => 'free', 'price' => 0, 'is_active' => true]);

    mockGoogleUser(id: 'google-888', email: 'welcome@example.com', name: 'Welcome Person');

    $this->withSession(['oauth_intent' => 'register', 'oauth_terms' => true])->get(route('google.callback'));

    $user = User::where('email', 'welcome@example.com')->firstOrFail();

    Notification::assertSentTo($user, WelcomeEmail::class);
    Notification::assertNotSentTo($user, WelcomeVerifyEmail::class);
});

test('returning google user is not sent another welcome email', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'returning-welcome@example.com',
        'google_id' => 'google-321',
    ]);

    mockGoogleUser(id: 'google-321', email: 'returning-welcome@example.com');

    $this->get(route('google.callback'));

    Notification::assertNotSentTo($user, WelcomeEmail::class);
});

test('new user registering with google flashes a welcome message for the toast', function () {
    Plan::factory()->create(['slug' => 'free', 'price' => 0, 'is_active' => true]);

    mockGoogleUser(id: 'google-889', email: 'toast@example.com', name: 'Toast Person');

    $response = $this->withSession(['oauth_intent' => 'register', 'oauth_terms' => true])->get(route('google.callback'));

    $response->assertSessionHas('success', 'Welcome to '.config('app.name').'! Your account is ready.');
});

test('returning google user does not see the welcome toast', function () {
    $user = User::factory()->create([
        'email' => 'returning-toast@example.com',
        'google_id' => 'google-322',
    ]);

    mockGoogleUser(id: 'google-322', email: 'returning-toast@example.com');

    $response = $this->get(route('google.callback'));

    $response->assertSessionMissing('success');
});

test('the welcome email uses the branded template', function () {
    $user = User::factory()->create(['name' => 'Amara']);

    $mail = (new WelcomeEmail)->toMail($user);

    expect($mail->subject)->toBe('Welcome to Nilo');
    expect($mail->view)->toBe('emails.welcome');

    $html = view($mail->view, $mail->viewData)->render();

    expect($html)
        ->toContain('Welcome aboard, Amara')
        ->toContain('logo-email.png')
        ->toContain('Create your company or organisation')
        ->toContain('icon-dashboard.png')
        ->toContain('pointer-hand.svg')
        ->toContain('>Dashboard</span>')
        ->toContain('Crafted by Resonantt')
        ->not->toContain('Verify email address');
});

test('existing password account is auto-linked by email on google sign in', function () {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'password' => Hash::make('secret-password'),
        'google_id' => null,
    ]);

    mockGoogleUser(id: 'google-555', email: 'existing@example.com');

    $response = $this->get(route('google.callback'));

    $this->assertAuthenticatedAs($user->fresh());
    $response->assertRedirect('/dashboard');

    expect($user->fresh()->google_id)->toBe('google-555');
    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
});

test('returning user matched by google_id is logged in without creating a duplicate', function () {
    $user = User::factory()->create([
        'email' => 'returning@example.com',
        'google_id' => 'google-777',
    ]);

    mockGoogleUser(id: 'google-777', email: 'returning@example.com');

    $response = $this->get(route('google.callback'));

    $this->assertAuthenticatedAs($user->fresh());
    $response->assertRedirect('/dashboard');
    expect(User::count())->toBe(1);
});

test('oauth failure redirects back to login with an error', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andThrow(new Exception('invalid state'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('google.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
});

test('an unverified google email cannot take over an existing password account', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com', 'google_id' => null]);

    mockGoogleUser(id: 'google-attacker', email: 'owner@example.com', emailVerified: false);

    $response = $this->withSession(['oauth_intent' => 'login'])->get(route('google.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    expect($owner->fresh()->google_id)->toBeNull();
});

test('an unverified google email cannot register a new account', function () {
    Plan::factory()->create(['slug' => 'free', 'price' => 0, 'is_active' => true]);

    mockGoogleUser(id: 'google-unverified', email: 'unverified@example.com', emailVerified: false);

    $response = $this->withSession(['oauth_intent' => 'register', 'oauth_terms' => true])->get(route('google.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    expect(User::where('email', 'unverified@example.com')->exists())->toBeFalse();
});

test('a google identity without an email is turned away', function () {
    mockGoogleUser(id: 'google-no-email', email: null);

    $response = $this->withSession(['oauth_intent' => 'register', 'oauth_terms' => true])->get(route('google.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    expect(User::where('google_id', 'google-no-email')->exists())->toBeFalse();
});
