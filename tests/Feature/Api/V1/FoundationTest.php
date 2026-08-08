<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The API's stateless contract, proved once here so the rest of the suite can
 * take it for granted: no token means 401 JSON, never a redirect to the login
 * page, and a bearer token is enough on its own.
 */
it('refuses an unauthenticated api call with json rather than a redirect', function () {
    $this->getJson('/api/v1/ping')
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('refuses an unauthenticated api call with json even without an accept header', function () {
    $response = $this->get('/api/v1/ping');

    $response->assertUnauthorized();

    expect($response->headers->get('content-type'))->toContain('application/json');
});

it('accepts a bearer token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Pixel 8')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/ping')
        ->assertSuccessful()
        ->assertJson(['ok' => true, 'user_id' => $user->id]);

    expect(PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(1);
});
