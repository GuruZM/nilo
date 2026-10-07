<?php

use App\Models\Currency;
use App\Services\CurrencyCatalog;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DatabaseSeeder;

it('seeds the currency catalog when the full seeder runs in production', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    config([
        'nilo.super_admin.email' => 'owner@resonantt.com',
        'nilo.super_admin.password' => 'a-long-strong-passphrase',
    ]);

    app(DatabaseSeeder::class)->run();

    expect(Currency::count())->toBe(count(CurrencyCatalog::ISO_4217))
        ->and(Currency::where('is_active', true)->orderBy('code')->pluck('code')->all())
        ->toBe(['EUR', 'GBP', 'USD', 'ZAR', 'ZMW']);
});

it('activates the defaults on a fresh install', function () {
    $this->seed(CurrencySeeder::class);

    expect(Currency::where('is_active', true)->orderBy('code')->pluck('code')->all())
        ->toBe(['EUR', 'GBP', 'USD', 'ZAR', 'ZMW']);
});

it('does not switch a deactivated default back on when seeded again', function () {
    $this->seed(CurrencySeeder::class);

    Currency::where('code', 'GBP')->update(['is_active' => false]);

    $this->seed(CurrencySeeder::class);

    expect(Currency::where('code', 'GBP')->first()->is_active)->toBeFalse()
        ->and(Currency::where('is_active', true)->count())->toBe(4);
});
