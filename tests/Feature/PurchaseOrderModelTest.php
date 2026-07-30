<?php

use App\Enums\DocumentType;
use App\Models\ExchangeRate;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Support\Carbon;

it('renders as a purchase order', function () {
    expect((new PurchaseOrder)->documentType())->toBe(DocumentType::PurchaseOrder);
});

it('addresses itself to a supplier, not a client', function () {
    $order = PurchaseOrder::factory()->create();

    expect($order->counterparty())->toBeInstanceOf(Supplier::class)
        ->and($order->counterparty()->id)->toBe($order->supplier_id);
});

it('lists the statuses an order moves through', function () {
    expect(PurchaseOrder::STATUSES)->toBe([
        'draft', 'sent', 'approved', 'received', 'cancelled',
    ]);
});

/**
 * FreezesExchangeRate returns early when no rate is stored, so a suite with an
 * empty rate table would never touch either column. This asserts the columns
 * exist and accept a write.
 */
it('freezes the exchange rate onto both columns when a rate exists', function () {
    $fetchedAt = Carbon::parse('2026-07-28 00:02:31');

    ExchangeRate::create([
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 18.7,
        'fetched_at' => $fetchedAt,
    ]);

    $order = PurchaseOrder::factory()->create(['currency_code' => 'ZMW'])->fresh();

    expect($order->exchange_rate_to_base)->toBe(18.7)
        ->and($order->exchange_rate_fetched_at->timestamp)->toBe($fetchedAt->timestamp);
});
