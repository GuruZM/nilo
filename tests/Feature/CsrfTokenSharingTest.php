<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Inertia\AlwaysProp;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Hand-rolled `fetch` calls read the `csrf-token` meta tag, which Blade writes
 * once per document. Inertia never re-renders `<head>`, so the token has to
 * travel as a prop for the client to refresh the tag from. CSRF rejection
 * itself is not asserted here: Laravel skips that middleware under tests.
 */
test('an inertia response carries the current csrf token', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('csrfToken', csrf_token()));
});

test('the csrf token reaches guest pages too, where the passkey login posts from', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('csrfToken', csrf_token())
        );
});

/**
 * A partial reload returns only the requested props. The token has to be exempt
 * from that filtering, or a `router.reload({ only: [...] })` would leave the
 * page unable to refresh a tag that may since have gone stale.
 */
test('the csrf token is shared as an always prop so partial reloads keep it', function () {
    $shared = (new HandleInertiaRequests)->share(request());

    expect($shared['csrfToken'])->toBeInstanceOf(AlwaysProp::class);
});
