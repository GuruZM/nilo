<?php

use App\Services\CurrencyConverter;

/**
 * Quote-per-USD rates, roughly realistic so the arithmetic is easy to eyeball.
 */
function converter(): CurrencyConverter
{
    return new CurrencyConverter([
        'ZMW' => 18.7,
        'EUR' => 0.92,
        'JPY' => 157.0,
        'ZAR' => 18.0,
    ]);
}

it('returns the amount unchanged for the same currency', function () {
    expect(converter()->convert(1500.0, 'ZMW', 'ZMW'))->toBe(1500.0);
});

it('returns the amount unchanged for the same currency even without any rates', function () {
    $converter = new CurrencyConverter([]);

    expect($converter->convert(1500.0, 'ZMW', 'ZMW'))->toBe(1500.0);
});

it('converts from the base currency', function () {
    expect(converter()->convert(100.0, 'USD', 'ZMW'))->toBe(1870.0);
});

it('converts to the base currency', function () {
    expect(converter()->convert(1870.0, 'ZMW', 'USD'))->toBe(100.0);
});

it('converts between two non-base currencies', function () {
    // 1870 ZMW is 100 USD, which is 92 EUR.
    expect(converter()->convert(1870.0, 'ZMW', 'EUR'))->toBe(92.0);
});

it('is case insensitive about currency codes', function () {
    expect(converter()->convert(100.0, 'usd', 'zmw'))->toBe(1870.0);
});

it('round-trips an amount back to where it started', function () {
    $converter = converter();

    $there = $converter->convert(2500.0, 'ZMW', 'JPY');
    $back = $converter->convert($there, 'JPY', 'ZMW');

    expect(round($back, 6))->toBe(2500.0);
});

it('prefers a frozen rate over the current one', function () {
    // Frozen at 15 ZMW per USD rather than today's 18.7.
    expect(converter()->convert(1500.0, 'ZMW', 'USD', 15.0))->toBe(100.0);
});

it('ignores a frozen rate that is zero or negative', function (float $frozen) {
    expect(converter()->convert(1870.0, 'ZMW', 'USD', $frozen))->toBe(100.0);
})->with([
    'zero' => 0.0,
    'negative' => -3.0,
]);

it('returns null when the source currency has no rate', function () {
    expect(converter()->convert(100.0, 'XOF', 'USD'))->toBeNull();
});

it('returns null when the target currency has no rate', function () {
    expect(converter()->convert(100.0, 'USD', 'XOF'))->toBeNull();
});

it('never falls back to a rate of one when a rate is missing', function () {
    $converted = converter()->convert(18700.0, 'ZMW', 'XOF');

    expect($converted)->not->toBe(18700.0)
        ->and($converted)->toBeNull();
});

it('treats a zero rate as missing', function () {
    $converter = new CurrencyConverter(['ZWG' => 0.0]);

    expect($converter->convert(100.0, 'ZWG', 'USD'))->toBeNull();
});

it('still converts a currency with no rate when the target is identical', function () {
    expect(converter()->convert(100.0, 'XOF', 'XOF'))->toBe(100.0);
});

it('reports whether a pair can be converted', function () {
    $converter = converter();

    expect($converter->canConvert('ZMW', 'EUR'))->toBeTrue()
        ->and($converter->canConvert('ZMW', 'XOF'))->toBeFalse();
});

it('reports the base currency as rate one', function () {
    expect(converter()->rateFor('USD'))->toBe(1.0);
});

it('knows when it holds no rates', function () {
    expect((new CurrencyConverter([]))->isEmpty())->toBeTrue()
        ->and(converter()->isEmpty())->toBeFalse();
});
