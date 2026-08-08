<?php

use App\Support\DocumentTotals;

/**
 * The money rules every priced document shares. These were four copies of one
 * private method until the mobile API needed them too; this is the test that
 * keeps the one remaining copy honest.
 */
function line(float $quantity, float $unitPrice, float $discount = 0): array
{
    return [
        'description' => 'Item',
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'discount' => $discount,
    ];
}

it('treats entered prices as tax inclusive', function () {
    $totals = DocumentTotals::compute([line(1, 5000)], 0, 16);

    expect($totals['total'])->toBe(5000.00)
        ->and($totals['subtotal'])->toBe(4310.34)
        ->and($totals['tax_total'])->toBe(689.66);
});

/**
 * Tax is derived by subtraction, never multiplication, precisely so this holds
 * for every input — a multiplied tax drifts a tambala at a time.
 */
it('always reconciles subtotal and tax back to exactly the total', function (float $price, float $rate) {
    $totals = DocumentTotals::compute([line(3, $price)], 0, $rate);

    expect(round($totals['subtotal'] + $totals['tax_total'], 2))->toBe($totals['total']);
})->with([
    [333.33, 16],
    [0.01, 16],
    [1999.99, 7.5],
    [10000, 0],
    [12.34, 100],
]);

it('carves out no tax at a zero rate', function () {
    $totals = DocumentTotals::compute([line(2, 250)], 0, 0);

    expect($totals['total'])->toBe(500.00)
        ->and($totals['subtotal'])->toBe(500.00)
        ->and($totals['tax_total'])->toBe(0.00);
});

it('takes line discounts off the gross before tax is carved out', function () {
    $totals = DocumentTotals::compute([line(2, 500, 100)], 0, 0);

    expect($totals['total'])->toBe(900.00)
        ->and($totals['line_discount_total'])->toBe(100.0)
        ->and($totals['discount_total'])->toBe(100.0);
});

it('adds the whole document discount to the line discounts', function () {
    $totals = DocumentTotals::compute([line(1, 1000, 50)], 200, 0);

    expect($totals['discount_total'])->toBe(250.0)
        ->and($totals['document_discount'])->toBe(200.0)
        ->and($totals['total'])->toBe(750.00);
});

/**
 * A discount larger than the document must not invert into a negative charge.
 */
it('floors the total at zero when the discount exceeds the goods', function () {
    $totals = DocumentTotals::compute([line(1, 100)], 500, 16);

    expect($totals['total'])->toBe(0.00)
        ->and($totals['subtotal'])->toBe(0.00)
        ->and($totals['tax_total'])->toBe(0.00);
});

/**
 * A line discount larger than the line itself is over-applied: the line floors
 * at zero, but the document total still subtracts the whole discount, so the
 * sum of the line totals and the document total disagree.
 *
 * Pinned rather than fixed. This is what the web app has always done — the
 * behaviour predates the extraction and the API inherits it unchanged, so the
 * two clients agree. Changing it is a product decision about what an
 * over-applied discount should mean, not a refactor.
 */
it('over-applies a line discount larger than the line, as it always has', function () {
    $totals = DocumentTotals::compute([line(1, 100, 500), line(1, 1000)], 0, 0);

    $sumOfLines = array_sum(array_column($totals['items'], 'line_total'));

    expect($totals['items'][0]['line_total'])->toBe(0)
        ->and($sumOfLines)->toBe(1000.0)
        ->and($totals['total'])->toBe(600.00);
});

it('stamps each line with its own total, zero tax and its position', function () {
    $totals = DocumentTotals::compute([line(2, 500, 100), line(1, 250)], 0, 16);

    expect($totals['items'][0]['line_total'])->toBe(900.0)
        ->and($totals['items'][0]['tax'])->toBe(0)
        ->and($totals['items'][0]['sort_order'])->toBe(0)
        ->and($totals['items'][1]['sort_order'])->toBe(1);
});

it('treats a missing line discount as zero', function () {
    $totals = DocumentTotals::compute([[
        'description' => 'Item',
        'quantity' => 1,
        'unit_price' => 400,
    ]], 0, 0);

    expect($totals['items'][0]['discount'])->toBe(0.0)
        ->and($totals['total'])->toBe(400.00);
});

it('handles fractional quantities', function () {
    $totals = DocumentTotals::compute([line(2.5, 400)], 0, 0);

    expect($totals['total'])->toBe(1000.00);
});

it('returns zeroes for a document with no lines', function () {
    $totals = DocumentTotals::compute([], 0, 16);

    expect($totals['total'])->toBe(0.00)
        ->and($totals['subtotal'])->toBe(0.00)
        ->and($totals['tax_total'])->toBe(0.00)
        ->and($totals['items'])->toBe([]);
});
