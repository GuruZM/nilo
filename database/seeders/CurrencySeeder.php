<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Currencies switched on for a fresh install. Everything else in the
     * ISO 4217 catalog is seeded inactive, ready to be ticked in settings.
     *
     * @var list<string>
     */
    protected const DEFAULT_ACTIVE = ['ZMW', 'USD', 'ZAR', 'EUR', 'GBP'];

    /**
     * Run the database seeds.
     *
     * The defaults are only switched on while nothing is active yet, so
     * re-seeding a live install never re-enables a currency someone turned off.
     */
    public function run(): void
    {
        $this->runQuietly('currencies:sync');

        if (Currency::query()->where('is_active', true)->exists()) {
            return;
        }

        Currency::query()
            ->whereIn('code', self::DEFAULT_ACTIVE)
            ->update(['is_active' => true]);
    }

    /**
     * Run an Artisan command without echoing its output through the seeder.
     *
     * Deliberately not called `callSilent`: `Seeder` already declares a public
     * method of that name, and redeclaring it `protected` is a fatal error that
     * fires the moment this class is loaded. This seeder could never have run.
     */
    private function runQuietly(string $command): void
    {
        $this->command
            ? $this->command->callSilent($command)
            : \Illuminate\Support\Facades\Artisan::call($command);
    }
}
