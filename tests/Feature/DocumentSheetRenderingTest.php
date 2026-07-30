<?php

use App\Enums\DocumentType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\InvoiceTemplate;
use Illuminate\Support\Facades\View;

/**
 * Renders the shared sheet directly, without going through a controller, so the
 * blade's own type handling is what is under test.
 */
function renderSheet(DocumentType $type, array $overrides = []): string
{
    return renderSheetWithRawType($type, $overrides);
}

/**
 * Same, but accepts whatever the caller wants to put in `documentType` — an
 * enum, a legacy string, or nothing at all. Pass `$omitType` to leave the key
 * out altogether, which is what InvoiceDocumentRenderer does.
 */
function renderSheetWithRawType(mixed $type, array $overrides = [], bool $omitType = false): string
{
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Acme Ltd']);

    $data = [
        'company' => $company,
        'client' => $client,
        'template' => new InvoiceTemplate(['settings' => []]),
        'currency' => Currency::query()->where('code', 'ZMW')->first(),
        'invoice' => array_merge([
            'number' => 'TEST-000001',
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-31',
            'valid_until' => '2026-07-31',
            'delivery_date' => '2026-07-05',
            'expected_date' => '2026-08-15',
            'subtotal' => 4310.34,
            'tax_total' => 689.66,
            'total' => 5000.00,
            'notes' => null,
            'terms' => null,
        ], $overrides),
        'items' => [
            ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 2500, 'line_total' => 5000],
        ],
        'settings' => app(App\Services\InvoiceDocumentRenderer::class)->normalizedSettings(null),
        'documentType' => $type,
        'mode' => 'preview',
    ];

    if ($omitType) {
        unset($data['documentType']);
    }

    return View::make('invoices.templates.default', $data)->render();
}

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

it('titles the sheet for every document type', function (DocumentType $type, string $title) {
    expect(renderSheet($type))->toContain('>'.$title.'<');
})->with([
    'invoice' => [DocumentType::Invoice, 'INVOICE'],
    'quotation' => [DocumentType::Quotation, 'QUOTATION'],
    'receipt' => [DocumentType::Receipt, 'RECEIPT'],
    'credit note' => [DocumentType::CreditNote, 'CREDIT NOTE'],
    'delivery note' => [DocumentType::DeliveryNote, 'DELIVERY NOTE'],
    'purchase order' => [DocumentType::PurchaseOrder, 'PURCHASE ORDER'],
]);

it('labels the second date from the type', function () {
    expect(renderSheet(DocumentType::Invoice))->toContain('Due: 2026-07-31')
        ->and(renderSheet(DocumentType::Quotation))->toContain('Valid until: 2026-07-31')
        ->and(renderSheet(DocumentType::DeliveryNote))->toContain('Delivered: 2026-07-05')
        ->and(renderSheet(DocumentType::PurchaseOrder))->toContain('Expected: 2026-08-15');
});

it('omits the second date row for types that have none', function () {
    expect(renderSheet(DocumentType::Receipt))
        ->not->toContain('Due:')
        ->not->toContain('Valid until:');
});

it('hides every money column on a delivery note', function () {
    $html = renderSheet(DocumentType::DeliveryNote);

    expect($html)
        ->not->toContain('GRAND TOTAL')
        ->not->toContain('Sub Total')
        ->not->toContain('2,500.00')
        ->not->toContain('5,000.00')
        ->and($html)->toContain('Consulting');
});

it('still shows money on every other type', function () {
    expect(renderSheet(DocumentType::Invoice))->toContain('GRAND TOTAL')
        ->and(renderSheet(DocumentType::PurchaseOrder))->toContain('GRAND TOTAL')
        ->and(renderSheet(DocumentType::CreditNote))->toContain('GRAND TOTAL');
});

it('addresses a delivery note to where the goods go', function () {
    expect(renderSheet(DocumentType::DeliveryNote))->toContain('Deliver to:')
        ->and(renderSheet(DocumentType::Invoice))->toContain('To:');
});

it('closes with wording matched to the type', function () {
    expect(renderSheet(DocumentType::Receipt))->toContain('Payment received with thanks.')
        ->and(renderSheet(DocumentType::PurchaseOrder))->toContain('quote this order number');
});

it('still accepts the legacy string document type', function () {
    /**
     * InvoiceDocumentRenderer and QuotationDocumentRenderer are untouched by
     * this plan and still pass 'quotation' as a plain string. The sheet must
     * keep understanding that, or every existing quotation breaks.
     */
    $html = renderSheetWithRawType('quotation');

    expect($html)->toContain('>QUOTATION<')->toContain('Valid until:');
});

it('falls back to an invoice for an unrecognised type', function () {
    expect(renderSheetWithRawType('nonsense'))->toContain('>INVOICE<');
});

it('falls back to an invoice when no type is given', function () {
    expect(renderSheetWithRawType(null))->toContain('>INVOICE<');
});

it('falls back to an invoice when the type key is absent altogether', function () {
    /**
     * InvoiceDocumentRenderer passes no `documentType` key at all, so the sheet
     * must never touch the variable in a way that assumes it was defined.
     */
    expect(renderSheetWithRawType(null, [], omitType: true))->toContain('>INVOICE<');
});
