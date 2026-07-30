<?php

use App\Models\Currency;
use App\Services\CurrencyCatalog;

it('populates the table from the catalog', function () {
    $this->artisan('currencies:sync')->assertSuccessful();

    expect(Currency::count())->toBe(count(CurrencyCatalog::ISO_4217));

    $kwacha = Currency::where('code', 'ZMW')->first();

    expect($kwacha->name)->toBe('Zambian Kwacha')
        ->and($kwacha->precision)->toBe(2);
});

it('inserts new currencies inactive, despite the column defaulting to active', function () {
    $this->artisan('currencies:sync')->assertSuccessful();

    expect(Currency::where('is_active', true)->count())->toBe(0);
});

it('does not duplicate rows when run twice', function () {
    $this->artisan('currencies:sync')->assertSuccessful();
    $this->artisan('currencies:sync')->assertSuccessful();

    expect(Currency::count())->toBe(count(CurrencyCatalog::ISO_4217))
        ->and(Currency::where('code', 'USD')->count())->toBe(1);
});

it('leaves is_active alone on currencies that already exist', function () {
    Currency::create([
        'code' => 'ZMW',
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);

    $this->artisan('currencies:sync')->assertSuccessful();

    expect(Currency::where('code', 'ZMW')->first()->is_active)->toBeTrue();
});

it('refreshes stale names and precision on existing rows', function () {
    Currency::create([
        'code' => 'JPY',
        'name' => 'Wrong Name',
        'symbol' => null,
        'precision' => 2,
        'is_active' => true,
    ]);

    $this->artisan('currencies:sync')->assertSuccessful();

    $yen = Currency::where('code', 'JPY')->first();

    expect($yen->name)->toBe('Japanese Yen')
        ->and($yen->precision)->toBe(0)
        ->and($yen->is_active)->toBeTrue();
});

it('reports how many were added versus refreshed', function () {
    $this->artisan('currencies:sync')
        ->expectsOutputToContain(sprintf('%d added', count(CurrencyCatalog::ISO_4217)))
        ->assertSuccessful();

    $this->artisan('currencies:sync')
        ->expectsOutputToContain('0 added')
        ->assertSuccessful();
});
