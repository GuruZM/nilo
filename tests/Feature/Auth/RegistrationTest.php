<?php

use App\Models\Plan;
use App\Models\User;
use App\Notifications\WelcomeEmail;
use App\Notifications\WelcomeVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('registration screen can be rendered when currencies are unavailable', function () {
    Schema::dropIfExists('currencies');

    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register and land on the dashboard with a free plan', function () {
    Plan::factory()->create(['slug' => 'free', 'price' => 0, 'is_active' => true]);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => true,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));

    $user = User::where('email', 'test@example.com')->firstOrFail();

    expect($user->hasActiveSubscription())->toBeTrue();
    expect($user->subscription->plan->slug)->toBe('free');
});

test('registration falls back to plan selection when no free plan exists', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => true,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));

    $user = User::where('email', 'test@example.com')->firstOrFail();

    expect($user->hasActiveSubscription())->toBeFalse();
});

test('users must agree to the terms to register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => false,
    ]);

    $response->assertSessionHasErrors('terms');
    $this->assertGuest();
});

test('registration sends the branded welcome verification email', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => true,
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();

    Notification::assertSentTo($user, WelcomeVerifyEmail::class);
    Notification::assertNotSentTo($user, WelcomeEmail::class);
});

test('the verification email uses the branded template', function () {
    $user = User::factory()->unverified()->create(['name' => 'Amara']);

    $mail = (new WelcomeVerifyEmail)->toMail($user);

    expect($mail->subject)->toBe('Welcome to Nilo, verify your email');
    expect($mail->view)->toBe('emails.welcome-verify');

    $html = view($mail->view, $mail->viewData)->render();

    expect($html)
        ->toContain('Welcome, Amara')
        ->toContain('logo-email.png')
        ->toContain('Verify email address')
        ->toContain('Crafted by Resonantt')
        ->toContain(e($mail->viewData['url']));
});

test('unverified users cannot use the system', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/subscription/select')->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get('/settings/profile')->assertRedirect(route('verification.notice'));
});
