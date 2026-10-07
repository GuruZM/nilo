<?php

it('defaults to light mode when no appearance cookie is set', function () {
    $this->get('/')
        ->assertOk()
        ->assertViewHas('appearance', 'light')
        ->assertSee("const appearance = 'light';", false)
        ->assertDontSee('class="dark"', false);
});

it('respects a saved appearance cookie', function (string $appearance) {
    $this->withUnencryptedCookie('appearance', $appearance)
        ->get('/')
        ->assertOk()
        ->assertViewHas('appearance', $appearance);
})->with(['light', 'dark', 'system']);

it('renders the dark class on the html element when dark mode is saved', function () {
    $this->withUnencryptedCookie('appearance', 'dark')
        ->get('/')
        ->assertOk()
        ->assertSee('class="dark"', false);
});
