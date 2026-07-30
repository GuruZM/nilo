<?php

use App\Services\CurrencyCatalog;

beforeEach(function () {
    $this->catalog = new CurrencyCatalog;
    $this->rows = collect($this->catalog->all())->keyBy('code');
});

it('returns every pinned ISO 4217 code', function () {
    expect($this->rows)->toHaveCount(count(CurrencyCatalog::ISO_4217));
});

it('pins only codes that ICU actually knows', function () {
    $bundle = ResourceBundle::create('en', 'ICUDATA-curr');
    $known = [];

    foreach ($bundle['Currencies'] as $code => $entry) {
        $known[$code] = true;
    }

    $unknown = array_values(array_filter(
        CurrencyCatalog::ISO_4217,
        fn (string $code) => ! isset($known[$code]),
    ));

    expect($unknown)->toBe([]);
});

it('has no duplicate codes', function () {
    expect(array_unique(CurrencyCatalog::ISO_4217))
        ->toHaveCount(count(CurrencyCatalog::ISO_4217));
});

it('resolves display names', function () {
    expect($this->rows['ZMW']['name'])->toBe('Zambian Kwacha')
        ->and($this->rows['USD']['name'])->toBe('US Dollar');
});

it('resolves minor units per currency', function (string $code, int $precision) {
    expect($this->rows[$code]['precision'])->toBe($precision);
})->with([
    'yen has no minor units' => ['JPY', 0],
    'dinar has three' => ['KWD', 3],
    'kwacha has two' => ['ZMW', 2],
    'dollar has two' => ['USD', 2],
]);

it('keeps distinct symbols and drops code-as-symbol fallbacks', function () {
    expect($this->rows['USD']['symbol'])->toBe('$')
        ->and($this->rows['EUR']['symbol'])->toBe('€')
        ->and($this->rows['ZMW']['symbol'])->toBeNull();
});

it('never returns a symbol identical to its code', function () {
    $echoed = collect($this->rows)
        ->filter(fn (array $row) => $row['symbol'] === $row['code'])
        ->keys()
        ->all();

    expect($echoed)->toBe([]);
});

it('excludes currencies that have been retired', function (string $code) {
    expect($this->rows->has($code))->toBeFalse();
})->with([
    'pre-redenomination kwacha' => 'ZMK',
    'deutschmark' => 'DEM',
    'kuna, replaced by the euro' => 'HRK',
    'cuban convertible peso' => 'CUC',
    'old leone' => 'SLL',
]);
