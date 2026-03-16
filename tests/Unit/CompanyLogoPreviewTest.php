<?php

use App\Models\Company;

it('normalizes company logo paths into preview urls', function (?string $logoPath, ?string $expectedUrl) {
    $company = new Company(['logo_path' => $logoPath]);

    expect($company->logo_url)->toBe($expectedUrl);
})->with([
    'missing logo' => [null, null],
    'relative path' => ['company-logos/acme.png', '/storage/company-logos/acme.png'],
    'public-prefixed path' => ['public/company-logos/acme.png', '/storage/company-logos/acme.png'],
    'storage-prefixed path' => ['storage/company-logos/acme.png', '/storage/company-logos/acme.png'],
    'full url' => ['https://cdn.example.com/acme.png', 'https://cdn.example.com/acme.png'],
]);

it('serializes the computed company logo url', function () {
    $company = new Company(['logo_path' => 'company-logos/acme.png']);

    expect($company->toArray()['logo_url'])->toBe('/storage/company-logos/acme.png');
});

it('uses the computed company logo url in the companies table and edit modal', function () {
    $page = file_get_contents(__DIR__.'/../../resources/js/pages/Companies/Index.tsx');

    expect($page)
        ->toContain('logo_url?: string | null;')
        ->toContain('const existingLogoUrl = company.logo_url ?? null;')
        ->toContain('company.logo_url ?? null;')
        ->not->toContain('`/storage/${company.logo_path}`');
});

it('hides the existing logo preview when the edit modal marks the logo for removal', function () {
    $page = file_get_contents(__DIR__.'/../../resources/js/pages/Companies/Index.tsx');

    expect($page)
        ->toContain('const showExistingLogo = ! form.data.remove_logo;')
        ->toContain('showExistingLogo ? existingLogoUrl : null')
        ->toContain("if (form.data.logo) {\n                                    form.setData('logo', null);")
        ->toContain("form.setData('remove_logo', true);");
});
