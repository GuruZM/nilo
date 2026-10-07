<?php

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\User;
use App\Services\InvoiceDocumentRenderer;
use App\Support\BankDetails;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * @return array{0: User, 1: Company}
 */
function bankDetailsTenant(): array
{
    $user = User::factory()->withSubscription()->create();
    $company = Company::factory()->create(['owner_id' => $user->id]);
    $user->companies()->attach($company->id, ['is_owner' => true, 'status' => 'active']);
    $user->forceFill(['current_company_id' => $company->id])->save();

    return [$user, $company];
}

/**
 * @return array{name: string, account_name: string, account_number: string, branch: string, swift_code: string}
 */
function zanacoAccount(): array
{
    return [
        'name' => 'Zanaco',
        'account_name' => 'Nilo Labs Ltd',
        'account_number' => '00123456789',
        'branch' => 'Cairo Road',
        'swift_code' => 'ZNCOZMLU',
    ];
}

/**
 * @param  array<string, mixed>  $settings
 */
function renderBankDetailsSheet(string $preset, array $settings = [], string $mode = 'preview'): string
{
    $resolved = app(InvoiceDocumentRenderer::class)->normalizedSettings(new InvoiceTemplate([
        'settings' => array_replace_recursive([
            'preset' => $preset,
            'visibility' => ['show_bank_details' => true],
            'bank' => zanacoAccount(),
        ], $settings),
    ]));

    return view('invoices.templates.default', [
        'company' => (object) ['name' => 'Nilo Labs Ltd', 'address' => 'Lusaka', 'email' => 'billing@nilo.ai'],
        'client' => (object) ['name' => 'Resonant Technologies', 'email' => 'accounts@resonant.tech'],
        'template' => new InvoiceTemplate(['settings' => $resolved]),
        'currency' => (object) ['code' => 'ZMW', 'symbol' => 'K', 'precision' => 2],
        'invoice' => ['number' => 'INV-000123', 'issue_date' => '2026-01-03', 'due_date' => '2026-01-10'],
        'items' => [['description' => 'Setup', 'quantity' => 1, 'unit_price' => 2500]],
        'settings' => $resolved,
        'documentType' => DocumentType::Invoice,
        'mode' => $mode,
        'autoPrint' => false,
    ])->render();
}

it('saves the bank details entered in the builder, trimmed', function (string $path) {
    [$user, $company] = bankDetailsTenant();

    $this->actingAs($user)
        ->post($path, [
            'name' => 'House style',
            'is_default' => true,
            'settings' => [
                'visibility' => ['show_bank_details' => true],
                'bank' => array_map(fn (string $value) => "  {$value}  ", zanacoAccount()),
            ],
        ])
        ->assertRedirect($path);

    $template = InvoiceTemplate::query()->where('company_id', $company->id)->sole();

    expect($template->settings['bank'])->toBe(zanacoAccount())
        ->and($template->settings['visibility']['show_bank_details'])->toBeTrue();
})->with(['/settings/invoice-templates', '/settings/quotation-templates']);

it('updates and clears bank details on an existing template', function () {
    [$user, $company] = bankDetailsTenant();
    $template = InvoiceTemplate::query()->create([
        'company_id' => $company->id,
        'type' => 'invoice',
        'name' => 'House style',
        'is_default' => true,
        'settings' => ['bank' => zanacoAccount()],
    ]);

    $this->actingAs($user)
        ->put("/settings/invoice-templates/{$template->id}", [
            'name' => 'House style',
            'settings' => ['bank' => ['account_number' => '99887766', 'swift_code' => '']],
        ])
        ->assertRedirect('/settings/invoice-templates');

    expect($template->fresh()->settings['bank'])->toBe([
        ...zanacoAccount(),
        'account_number' => '99887766',
        'swift_code' => '',
    ]);
});

it('keeps only the known bank fields', function () {
    [$user, $company] = bankDetailsTenant();

    $this->actingAs($user)
        ->post('/settings/invoice-templates', [
            'name' => 'House style',
            'settings' => ['bank' => ['name' => 'Zanaco', 'html' => '<script>alert(1)</script>']],
        ])
        ->assertRedirect('/settings/invoice-templates');

    expect(InvoiceTemplate::query()->where('company_id', $company->id)->sole()->settings['bank'])
        ->toBe(['name' => 'Zanaco', 'account_name' => '', 'account_number' => '', 'branch' => '', 'swift_code' => '']);
});

it('rejects bank details that are too long or not text', function (string $field, mixed $value) {
    [$user] = bankDetailsTenant();

    $this->actingAs($user)
        ->post('/settings/invoice-templates', [
            'name' => 'House style',
            'settings' => ['bank' => [$field => $value]],
        ])
        ->assertSessionHasErrors("settings.bank.{$field}");

    expect(InvoiceTemplate::query()->count())->toBe(0);
})->with([
    'long bank name' => ['name', str_repeat('a', 121)],
    'long account number' => ['account_number', str_repeat('1', 61)],
    'array account name' => ['account_name', ['nested']],
]);

it('prints the bank details on the wave sheet', function () {
    $html = renderBankDetailsSheet('wave_premium');

    expect($html)->toContain('Bank Details')
        ->and($html)->toContain('Bank: Zanaco')
        ->and($html)->toContain('Account name: Nilo Labs Ltd')
        ->and($html)->toContain('Account number: 00123456789')
        ->and($html)->toContain('Branch: Cairo Road')
        ->and($html)->toContain('SWIFT code: ZNCOZMLU')
        ->and($html)->not->toContain('Bank: —');
});

it('prints the bank details on the meridian sheet', function () {
    $html = renderBankDetailsSheet('meridian');

    expect($html)->toContain('Payment details')
        ->and($html)->toContain('Bank: Zanaco')
        ->and($html)->toContain('Account number: 00123456789')
        ->and($html)->toContain('SWIFT code: ZNCOZMLU');
});

it('leaves the bank details off when they are switched off', function (string $preset) {
    $html = renderBankDetailsSheet($preset, ['visibility' => ['show_bank_details' => false]]);

    expect($html)->not->toContain('00123456789')
        ->and($html)->not->toContain('Bank Details')
        ->and($html)->not->toContain('Payment details');
})->with(['wave_premium', 'meridian']);

it('prints no empty bank block when none were entered', function (string $preset) {
    $html = renderBankDetailsSheet($preset, ['bank' => array_fill_keys(array_keys(zanacoAccount()), '')]);

    expect($html)->not->toContain('Bank Details')
        ->and($html)->not->toContain('Payment details')
        ->and($html)->not->toContain('Bank: —');
})->with(['wave_premium', 'meridian']);

it('skips the bank fields left blank', function () {
    $html = renderBankDetailsSheet('wave_premium', ['bank' => ['branch' => '', 'swift_code' => '  ']]);

    expect($html)->toContain('Account number: 00123456789')
        ->and($html)->not->toContain('Branch:')
        ->and($html)->not->toContain('SWIFT code:');
});

it('escapes markup typed into the bank details', function (string $preset) {
    $html = renderBankDetailsSheet($preset, ['bank' => ['name' => '<img src=x onerror=alert(1)>']]);

    expect($html)->not->toContain('<img src=x onerror=alert(1)>')
        ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;');
})->with(['wave_premium', 'meridian']);

it('keeps a single item meridian document on one PDF page with every bank field filled', function () {
    $pdf = Pdf::loadHTML(renderBankDetailsSheet('meridian', mode: 'pdf'))->setPaper('a4')->output();

    expect(preg_match_all('#/Type\s*/Page[^s]#', $pdf))->toBe(1);
});

it('labels only the filled bank fields, in a fixed order', function () {
    expect(BankDetails::lines(['swift_code' => 'ZNCOZMLU', 'name' => ' Zanaco ', 'branch' => '', 'other' => 'x']))
        ->toBe([
            ['label' => 'Bank', 'value' => 'Zanaco'],
            ['label' => 'SWIFT code', 'value' => 'ZNCOZMLU'],
        ])
        ->and(BankDetails::lines(null))->toBe([]);
});
