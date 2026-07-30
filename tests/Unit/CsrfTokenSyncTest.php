<?php

test('the client refreshes the csrf meta tag from every fresh inertia response', function () {
    $app = file_get_contents(__DIR__.'/../../resources/js/app.tsx');
    $csrf = file_get_contents(__DIR__.'/../../resources/js/lib/csrf.ts');

    expect($app)
        ->toContain("import { syncCsrfToken } from './lib/csrf';")
        // `navigate` also fires for history restores, which replay cached props
        // holding whatever the token was when that page was fetched.
        ->toContain("router.on('success', (event) => {")
        ->toContain('syncCsrfToken(event.detail.page.props.csrfToken);')
        ->not->toContain("router.on('navigate'");

    expect($csrf)
        ->toContain('meta[name="csrf-token"]')
        // A partial response without the prop must leave the tag alone rather
        // than blanking it.
        ->toContain("if (typeof token !== 'string' || token === '') {");
});

test('the pages that build their own fetch requests read the tag the client keeps current', function () {
    $consumers = [
        'resources/js/pages/Invoices/Create.tsx',
        'resources/js/pages/Quotations/Create.tsx',
        'resources/js/pages/Companies/Index.tsx',
    ];

    foreach ($consumers as $consumer) {
        expect(file_get_contents(__DIR__.'/../../'.$consumer))
            ->toContain('meta[name="csrf-token"]')
            ->and(file_get_contents(__DIR__.'/../../'.$consumer))
            ->toContain('X-CSRF-TOKEN');
    }
});
