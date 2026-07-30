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
     */
    public function run(): void
    {
        $this->callSilent('currencies:sync');

        Currency::query()
            ->whereIn('code', self::DEFAULT_ACTIVE)
            ->update(['is_active' => true]);
    }

    /**
     * Run an Artisan command without echoing its output through the seeder.
     */
    protected function callSilent(string $command): void
    {
        $this->command
            ? $this->command->callSilent($command)
            : \Illuminate\Support\Facades\Artisan::call($command);
    }
}
