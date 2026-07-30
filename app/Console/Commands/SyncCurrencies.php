<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Services\CurrencyCatalog;
use Illuminate\Console\Command;

class SyncCurrencies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currencies:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate the currencies table from the ISO 4217 catalog';

    public function __construct(protected CurrencyCatalog $catalog)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rows = $this->catalog->all();

        if ($rows === []) {
            $this->error('The ISO 4217 catalog is empty. Is the intl extension loaded?');

            return self::FAILURE;
        }

        $existing = Currency::query()->pluck('code')->all();
        $existing = array_flip($existing);

        $added = 0;

        $values = [];

        foreach ($rows as $row) {
            if (! isset($existing[$row['code']])) {
                $added++;
            }

            /**
             * `is_active` is only ever written on insert. Overwriting it on
             * existing rows would let a routine sync switch a business's
             * currencies on or off behind its back.
             */
            $values[] = $row + ['is_active' => false];
        }

        Currency::upsert($values, ['code'], ['name', 'symbol', 'precision']);

        $this->info(sprintf(
            '%d currencies synced (%d added, %d refreshed).',
            count($values),
            $added,
            count($values) - $added,
        ));

        return self::SUCCESS;
    }
}
