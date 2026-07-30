<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the terms of service page for guests', function () {
    $this->get('/terms')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('terms'));
});

it('renders the privacy policy page for guests', function () {
    $this->get('/privacy')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('privacy'));
});
