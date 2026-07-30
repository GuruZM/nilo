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
        ->and(DocumentType::DeliveryNote->secondDateLabel())->toBe('Delivered');
});

it('addresses a delivery note to where the goods go', function () {
    expect(DocumentType::DeliveryNote->counterpartyLabel())->toBe('Deliver to:')
        ->and(DocumentType::Invoice->counterpartyLabel())->toBe('To:');
});
