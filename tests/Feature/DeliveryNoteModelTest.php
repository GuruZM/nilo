<?php

use App\Enums\DocumentType;
use App\Models\DeliveryNote;

it('renders as a delivery note', function () {
    expect((new DeliveryNote)->documentType())->toBe(DocumentType::DeliveryNote);
});

it('never shows prices', function () {
    expect((new DeliveryNote)->documentType()->showsPrices())->toBeFalse();
});

it('lists the statuses a delivery moves through', function () {
    expect(DeliveryNote::STATUSES)->toBe(['draft', 'dispatched', 'delivered']);
});
