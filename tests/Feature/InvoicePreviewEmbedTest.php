<?php

use App\Models\Currency;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

/**
 * The create page renders the preview inside an iframe already sized to A4, so the
 * embedded document must hold 210mm instead of reflowing into the narrower frame.
 */
function previewInvoice(bool $embedded): string
{
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $payload = invoicePayload($client, $template, false);
    $payload['items'] = [
        ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 2500, 'discount' => 0],
    ];

    if ($embedded) {
        $payload['embed'] = true;
    }

    $response = test()->actingAs($user)->post('/invoices/preview', $payload);
    $response->assertSuccessful();

    return $response->getContent();
}

it('keeps the embedded preview at a full A4 width instead of shrinking to the frame', function () {
    $html = previewInvoice(embedded: true);

    expect($html)->toContain('max-width: none;')
        ->and($html)->toContain('width: 210mm;');
});

it('drops the standalone page chrome when the preview is embedded', function () {
    $html = previewInvoice(embedded: true);

    expect($html)->not->toContain('<div class="screen-toolbar">')
        ->and($html)->not->toContain('Download PDF')
        ->and($html)->toContain('.page-wrap{ padding: 0; }');
});

it('still serves the standalone preview with its toolbar when not embedded', function () {
    $html = previewInvoice(embedded: false);

    expect($html)->toContain('<div class="screen-toolbar">')
        ->and($html)->not->toContain('max-width: none;');
});

it('renders the same invoice content in both preview modes', function () {
    expect(previewInvoice(embedded: true))->toContain('5,000.00')
        ->and(previewInvoice(embedded: false))->toContain('5,000.00');
});
