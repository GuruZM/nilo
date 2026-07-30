<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    if (! Features::canManagePasskeys()) {
        $this->markTestSkipped('Passkeys are not enabled.');
    }
});

test('a guest can fetch the passkey login challenge', function () {
    $this->getJson(route('passkey.login-options'))
        ->assertOk()
        ->assertJsonStructure(['options' => ['challenge', 'rpId']]);
});

test('the login challenge is closed to signed in users', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->get(route('passkey.login-options'))
        ->assertRedirect(config('fortify.home'));
});

test('a login attempt with a bogus credential does not sign anyone in', function () {
    $this->postJson(route('passkey.login'), [
        'credential' => ['id' => 'not-a-real-credential'],
    ])->assertStatus(422);

    $this->assertGuest();
});

test('the passkey login routes are rate limited', function () {
    foreach (range(1, 11) as $ignored) {
        $response = $this->getJson(route('passkey.login-options'));
    }

    $response->assertTooManyRequests();
});
