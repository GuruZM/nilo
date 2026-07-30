<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('password update page is displayed', function () {
    $user = User::factory()->withSubscription()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('password.edit'));

    $response->assertStatus(200);
});

test('password can be updated', function () {
    $user = User::factory()->withSubscription()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('password.edit'))
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('password.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('a social user with no password can set a first one without a current password', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('password.edit'))
        ->put(route('password.update'), [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('password.edit'));

    expect(Hash::check('brand-new-password', $user->refresh()->password))->toBeTrue();
});

test('the password settings page reports whether the account already has a password', function () {
    $user = User::factory()->withSubscription()->create([
        'password' => null,
        'google_id' => 'google-1',
    ]);

    $this->actingAs($user)
        ->get(route('password.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/password')
            ->where('hasPassword', false)
        );
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->withSubscription()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('password.edit'))
        ->put(route('password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('password.edit'));
});
