<?php

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\User;
use App\Services\InvoiceDocumentRenderer;
use App\Support\QrCodeSvg;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $settings
 * @param  array<string, mixed>  $document
 * @param  array<string, mixed>  $company
 */
function renderMeridian(DocumentType $type, array $settings = [], array $document = [], string $mode = 'preview', array $company = []): string
{
    $resolved = app(InvoiceDocumentRenderer::class)->normalizedSettings(new InvoiceTemplate([
        'settings' => array_replace_recursive([
            'preset' => 'meridian',
            'brand' => ['primary' => '#2B2D33', 'accent' => '#5570A2', 'header' => '#073F7A', 'font' => 'Inter'],
            'content' => ['qr_url' => 'https://www.resonantt.com/', 'tagline' => 'Clear and Continuing'],
        ], $settings),
    ]));

    return view('invoices.templates.default', [
        'company' => (object) ($company + ['name' => 'Resonant Technologies', 'address' => 'Lusaka, Zambia', 'email' => 'sales@resonantt.com']),
        'client' => (object) ['name' => 'Acme Holdings Ltd', 'email' => 'accounts@acme.co.zm'],
        'template' => new InvoiceTemplate(['settings' => $resolved, 'terms_html' => '<p>Valid for 30 days.</p>']),
        'currency' => (object) ['code' => 'ZMW', 'symbol' => 'K', 'precision' => 2],
        'invoice' => array_merge([
            'number' => 'DOC-000142',
            'title' => 'Smart Invoice Integration',
            'issue_date' => '2026-10-02',
            'due_date' => '2026-10-16',
            'valid_until' => '2026-11-01',
            'subtotal' => 20000,
            'tax_total' => 3200,
            'tax_percent' => 16,
            'total' => 23200,
        ], $document),
        'items' => [['description' => 'Invoice Engine & Templates', 'quantity' => 1, 'unit_price' => 20000, 'line_total' => 20000]],
        'settings' => $resolved,
        'documentType' => $type,
        'mode' => $mode,
    ])->render();
}

it('draws the meridian sheet for invoices and quotations', function (DocumentType $type, string $secondDate) {
    $html = renderMeridian($type);

    expect($html)->toContain('class="sheet meridian"')
        ->and($html)->toContain($type->documentTitle().'<span class="dot">.</span>')
        ->and($html)->toContain('DOC-000142')
        ->and($html)->toContain($secondDate)
        ->and($html)->toContain('Smart Invoice Integration')
        ->and($html)->toContain('Clear and Continuing')
        ->and($html)->toContain('VAT (16%)')
        ->and($html)->toContain('K 23,200.00')
        ->and($html)->not->toContain('class="hero');
})->with([
    'invoice' => [DocumentType::Invoice, '16 October 2026'],
    'quotation' => [DocumentType::Quotation, '01 November 2026'],
]);

it('puts a QR code for the configured link on the sheet', function () {
    $html = renderMeridian(DocumentType::Quotation);

    expect($html)->toContain('alt="QR code"')
        ->and($html)->toContain(QrCodeSvg::dataUri('https://www.resonantt.com/', '#073F7A'))
        ->and($html)->toContain('resonantt.com');
});

it('falls back to the company initials when there is no QR link', function () {
    $html = renderMeridian(DocumentType::Invoice, ['content' => ['qr_url' => '']]);

    expect($html)->not->toContain('alt="QR code"')
        ->and($html)->toContain('<div class="initials">RT</div>');
});

it('hides the QR code when it is switched off', function () {
    $html = renderMeridian(DocumentType::Invoice, ['visibility' => ['show_qr' => false]]);

    expect($html)->not->toContain('alt="QR code"');
});

it('drops every price from a delivery note', function () {
    $html = renderMeridian(DocumentType::DeliveryNote);

    expect($html)->toContain('DELIVERY NOTE')
        ->and($html)->not->toContain('Unit price')
        ->and($html)->not->toContain('K 23,200.00');
});

it('shows a discount line only when the document carries one', function () {
    expect(renderMeridian(DocumentType::Invoice))->not->toContain('>Discount<')
        ->and(renderMeridian(DocumentType::Invoice, document: ['discount_total' => 500]))->toContain('- K 500.00');
});

it('leaves out the VAT line when the document carries no tax', function () {
    $html = renderMeridian(DocumentType::Invoice, document: ['subtotal' => 15000, 'tax_total' => 0, 'tax_percent' => 0, 'total' => 15000]);

    expect($html)->not->toContain('>VAT')
        ->and($html)->toContain('K 15,000.00');
});

it('leaves the wave sheet untouched for the other presets', function () {
    $html = renderMeridian(DocumentType::Invoice, ['preset' => 'wave_premium']);

    expect($html)->toContain('class="hero')
        ->and($html)->not->toContain('class="sheet meridian"');
});

it('fits a single item document on one PDF page', function (DocumentType $type) {
    $pdf = Pdf::loadHTML(renderMeridian($type, mode: 'pdf'))->setPaper('a4')->output();

    expect($pdf)->toStartWith('%PDF')
        ->and(preg_match_all('#/Type\s*/Page[^s]#', $pdf))->toBe(1);
})->with([DocumentType::Invoice, DocumentType::Quotation]);

/**
 * A 1×1 RGB PNG; without an alpha channel DomPDF decodes it without GD.
 */
function meridianLogoPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGOQti0CAAFBAMt92zIGAAAAAElFTkSuQmCC');
}

/**
 * On the server each release links `storage` to a shared directory, outside
 * DomPDF's chroot, so a logo handed over as a file path printed as its alt text.
 */
it('draws the logo into the PDF even when storage sits outside the DomPDF chroot', function () {
    Storage::fake('public');
    Storage::disk('public')->put('company-logos/acme.png', meridianLogoPng());
    config(['dompdf.options.chroot' => app_path()]);

    $html = renderMeridian(DocumentType::Quotation, ['content' => ['qr_url' => '']], mode: 'pdf', company: ['logo_path' => 'company-logos/acme.png']);
    $pdf = Pdf::loadHTML($html)->setPaper('a4')->output();

    expect($html)->toContain('alt="Logo"')
        ->and($html)->toContain('data:image/png;base64,')
        ->and($pdf)->toMatch('#/Subtype\s*/Image#');
});

it('pins the tile over one panel instead of splitting the panel across cells', function (string $mode) {
    $html = renderMeridian(DocumentType::Invoice, mode: $mode);

    expect($html)->toContain('<div class="side-lead"></div>')
        ->and($html)->toContain('<div class="tile">')
        ->and($html)->not->toContain('rowspan=');
})->with(['preview', 'pdf']);

it('backs the item header with one bar only in the PDF', function () {
    expect(renderMeridian(DocumentType::Invoice, mode: 'pdf'))->toContain('<div class="items-head"></div>')
        ->and(renderMeridian(DocumentType::Invoice))->not->toContain('<div class="items-head"></div>');
});

it('inlines the logo into the default sheet PDF as well', function () {
    Storage::fake('public');
    Storage::disk('public')->put('company-logos/acme.png', meridianLogoPng());

    $company = ['logo_path' => 'company-logos/acme.png'];

    expect(renderMeridian(DocumentType::Invoice, ['preset' => 'wave_premium'], mode: 'pdf', company: $company))
        ->toContain('data:image/png;base64,')
        ->and(renderMeridian(DocumentType::Invoice, ['preset' => 'wave_premium'], company: $company))
        ->toContain('/storage/company-logos/acme.png');
});

it('encodes nothing for a blank link', function () {
    expect(QrCodeSvg::make('   '))->toBeNull()
        ->and(QrCodeSvg::make('https://example.com', 'red; onload=x'))->toContain('fill="#000000"');
});

it('saves the QR link with a scheme so phones open it', function (string $entered, string $stored) {
    $user = User::factory()->withSubscription()->create();
    $company = Company::factory()->create(['owner_id' => $user->id]);
    $user->companies()->attach($company->id, ['is_owner' => true, 'status' => 'active']);
    $user->forceFill(['current_company_id' => $company->id])->save();

    $this->actingAs($user)
        ->post('/settings/quotation-templates', [
            'name' => 'Meridian',
            'is_default' => true,
            'settings' => [
                'preset' => 'meridian',
                'content' => ['qr_url' => $entered, 'tagline' => '  Clear and Continuing  '],
            ],
        ])
        ->assertRedirect('/settings/quotation-templates');

    $template = InvoiceTemplate::query()->where('company_id', $company->id)->where('type', 'quotation')->sole();

    expect($template->settings['preset'])->toBe('meridian')
        ->and($template->settings['content'])->toBe(['qr_url' => $stored, 'tagline' => 'Clear and Continuing'])
        ->and($template->settings['visibility']['show_qr'])->toBeTrue();
})->with([
    'bare domain' => ['resonantt.com', 'https://resonantt.com'],
    'full url' => ['https://www.resonantt.com/', 'https://www.resonantt.com/'],
    'other scheme' => ['mailto:sales@resonantt.com', 'mailto:sales@resonantt.com'],
    'blank' => ['', ''],
]);
