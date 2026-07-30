<?php

use App\Enums\DocumentType;

it('gives every type a distinct number prefix', function () {
    $prefixes = array_map(
        fn (DocumentType $type) => $type->numberPrefix(),
        DocumentType::cases(),
    );

    expect($prefixes)->toBe(['INV', 'QUO', 'RCP', 'CRN', 'DN', 'PO'])
        ->and(array_unique($prefixes))->toHaveCount(6);
});

it('titles the printed sheet from the label', function () {
    expect(DocumentType::CreditNote->documentTitle())->toBe('CREDIT NOTE')
        ->and(DocumentType::Invoice->documentTitle())->toBe('INVOICE');
});

it('hides prices on delivery notes only', function () {
    $priceless = array_values(array_filter(
        DocumentType::cases(),
        fn (DocumentType $type) => ! $type->showsPrices(),
    ));

    expect($priceless)->toBe([DocumentType::DeliveryNote])
        ->and(DocumentType::Invoice->showsPrices())->toBeTrue();
});

it('routes only purchase orders to a supplier', function () {
    $supplierTypes = array_values(array_filter(
        DocumentType::cases(),
        fn (DocumentType $type) => $type->usesSupplier(),
    ));

    expect($supplierTypes)->toBe([DocumentType::PurchaseOrder]);
});

it('knows which column carries the second date', function () {
    expect(DocumentType::Invoice->secondDateField())->toBe('due_date')
        ->and(DocumentType::Quotation->secondDateField())->toBe('valid_until')
        ->and(DocumentType::DeliveryNote->secondDateField())->toBe('delivery_date')
        ->and(DocumentType::PurchaseOrder->secondDateField())->toBe('expected_date')
        ->and(DocumentType::Receipt->secondDateField())->toBeNull()
        ->and(DocumentType::CreditNote->secondDateField())->toBeNull();
});

it('pairs every second-date column with a label', function (DocumentType $type) {
    expect($type->secondDateLabel() === null)->toBe($type->secondDateField() === null);
})->with(DocumentType::cases());

it('labels the second date in the type\'s own words', function () {
    expect(DocumentType::Invoice->secondDateLabel())->toBe('Due')
        ->and(DocumentType::Quotation->secondDateLabel())->toBe('Valid until')
        ->and(DocumentType::DeliveryNote->secondDateLabel())->toBe('Delivered')
        ->and(DocumentType::PurchaseOrder->secondDateLabel())->toBe('Expected');
});

it('addresses a delivery note to where the goods go', function () {
    expect(DocumentType::DeliveryNote->counterpartyLabel())->toBe('Deliver to:')
        ->and(DocumentType::Invoice->counterpartyLabel())->toBe('To:');
});

it('closes each document with wording matched to its type', function () {
    expect(DocumentType::Invoice->closingLine())
        ->toBe('Thank you for your business. Kindly settle within due date.')
        ->and(DocumentType::Quotation->closingLine())
        ->toBe('Thank you for the opportunity. This quotation is valid until the date shown above.')
        ->and(DocumentType::Receipt->closingLine())
        ->toBe('Payment received with thanks.')
        ->and(DocumentType::CreditNote->closingLine())
        ->toBe('This credit note has been applied to the invoice shown above.')
        ->and(DocumentType::DeliveryNote->closingLine())
        ->toBe('Please check the goods on arrival and sign below to confirm receipt.')
        ->and(DocumentType::PurchaseOrder->closingLine())
        ->toBe('Please confirm acceptance and quote this order number on your invoice.');
});

it('words each type for prose', function () {
    $labels = array_map(fn (DocumentType $type) => $type->label(), DocumentType::cases());

    expect($labels)->toBe([
        'invoice', 'quotation', 'receipt', 'credit note', 'delivery note', 'purchase order',
    ]);
});
