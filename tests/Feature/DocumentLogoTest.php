<?php

use App\Support\DocumentLogo;
use Illuminate\Support\Facades\Storage;

/**
 * A 1×1 PNG, so the disk reports a real image MIME type.
 */
function tinyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGOQti0CAAFBAMt92zIGAAAAAElFTkSuQmCC');
}

beforeEach(function () {
    Storage::fake('public');
});

it('inlines a stored logo into the PDF', function (string $logoPath) {
    Storage::disk('public')->put('company-logos/acme.png', tinyPng());

    expect(DocumentLogo::src($logoPath, true))->toBe('data:image/png;base64,'.base64_encode(tinyPng()));
})->with([
    'disk path' => 'company-logos/acme.png',
    'storage url path' => '/storage/company-logos/acme.png',
]);

it('links a stored logo for the browser', function (string $logoPath) {
    expect(DocumentLogo::src($logoPath, false))->toBe(asset('storage/company-logos/acme.png'));
})->with([
    'disk path' => 'company-logos/acme.png',
    'storage url path' => '/storage/company-logos/acme.png',
]);

it('passes a remote logo to the browser but leaves it out of the PDF', function () {
    expect(DocumentLogo::src('https://cdn.acme.test/mark.png', false))->toBe('https://cdn.acme.test/mark.png')
        ->and(DocumentLogo::src('https://cdn.acme.test/mark.png', true))->toBeNull();
});

it('has no logo to draw when none is stored', function (?string $logoPath) {
    expect(DocumentLogo::src($logoPath, true))->toBeNull()
        ->and(DocumentLogo::src($logoPath, false))->toBeNull();
})->with([
    'null' => null,
    'blank' => '   ',
]);

it('leaves a missing or non-image file out of the PDF', function () {
    Storage::disk('public')->put('company-logos/notes.txt', 'not an image');

    expect(DocumentLogo::src('company-logos/gone.png', true))->toBeNull()
        ->and(DocumentLogo::src('company-logos/notes.txt', true))->toBeNull();
});
