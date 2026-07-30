<?php

use App\Models\InvoiceTemplate;

/**
 * @param  'split'|'left'|'center'  $header
 */
function renderInvoiceTemplateWithHeader(string $header, array $brandOverrides = []): string
{
    $settings = [
        'preset' => 'wave_premium',
        'brand' => array_replace([
            'primary' => '#111827',
            'accent' => '#F59E0B',
            'header' => '#111827',
            'font' => 'Inter',
        ], $brandOverrides),
        'layout' => ['header' => $header, 'table' => 'striped', 'density' => 'normal'],
        'visibility' => [
            'show_logo' => true,
            'show_client_email' => true,
            'show_contact_person' => true,
            'show_terms' => true,
            'show_notes' => true,
            'show_bank_details' => false,
            'show_signature' => false,
        ],
    ];

    return view('invoices.templates.default', [
        'company' => (object) ['name' => 'Nilo Labs Ltd', 'address' => 'Lusaka', 'email' => 'billing@nilo.ai'],
        'client' => (object) ['name' => 'Resonant Technologies', 'email' => 'accounts@resonant.tech'],
        'template' => new InvoiceTemplate(['settings' => $settings]),
        'currency' => (object) ['code' => 'ZMW', 'symbol' => 'K', 'precision' => 2],
        'invoice' => ['number' => 'INV-000123', 'issue_date' => '2026-01-03', 'due_date' => '2026-01-10'],
        'items' => [['description' => 'Setup', 'quantity' => 1, 'unit_price' => 2500]],
        'settings' => $settings,
        'mode' => 'preview',
        'autoPrint' => false,
    ])->render();
}

it('renders the split header side by side with the document meta aligned right', function () {
    $html = renderInvoiceTemplateWithHeader('split');

    expect($html)->toContain('class="hero "')
        ->and($html)->toContain('class="right-align"');
});

it('renders the left header stacked and left aligned', function () {
    $html = renderInvoiceTemplateWithHeader('left');

    expect($html)->toContain('class="hero hero-left"')
        ->and($html)->not->toContain('class="right-align"');
});

it('renders the centered header stacked and centered', function () {
    $html = renderInvoiceTemplateWithHeader('center');

    expect($html)->toContain('class="hero hero-center"')
        ->and($html)->not->toContain('class="right-align"');
});

it('paints the header band with the header color, independently of the primary color', function () {
    $html = renderInvoiceTemplateWithHeader('split', [
        'primary' => '#111827',
        'header' => '#1E3A8A',
    ]);

    expect($html)->toContain('--header: #1E3A8A;')
        ->and($html)->toContain('--primary: #111827;')
        ->and($html)->toContain('background: var(--header);');
});

it('falls back to the primary color for templates saved without a header color', function () {
    $settings = [
        'brand' => ['primary' => '#0F172A', 'accent' => '#22C55E', 'font' => 'Inter'],
        'layout' => ['header' => 'split', 'table' => 'striped', 'density' => 'normal'],
    ];

    $html = view('invoices.templates.default', [
        'company' => (object) ['name' => 'Nilo Labs Ltd'],
        'client' => (object) ['name' => 'Resonant Technologies'],
        'template' => new InvoiceTemplate(['settings' => $settings]),
        'currency' => (object) ['code' => 'ZMW', 'symbol' => 'K', 'precision' => 2],
        'invoice' => ['number' => 'INV-000123'],
        'items' => [],
        'settings' => $settings,
        'mode' => 'preview',
        'autoPrint' => false,
    ])->render();

    expect($html)->toContain('--header: #0F172A;');
});

it('produces different markup for each header layout', function () {
    $split = renderInvoiceTemplateWithHeader('split');
    $left = renderInvoiceTemplateWithHeader('left');
    $center = renderInvoiceTemplateWithHeader('center');

    expect($split)->not->toEqual($left)
        ->and($left)->not->toEqual($center)
        ->and($split)->not->toEqual($center);
});
