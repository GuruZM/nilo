<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use App\Services\ExchangeRateSynchronizer;
use Illuminate\Console\Command;

class SyncExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange-rates:sync {--base= : The currency to quote rates against}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh stored FX rates from the upstream provider';

    public function __construct(protected ExchangeRateSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $base = strtoupper((string) ($this->option('base') ?: ExchangeRate::BASE));

        $result = $this->synchronizer->sync($base);

        if (! $result->successful) {
            $this->error("Exchange rate sync failed: {$result->error}");
            $this->line('Existing rates were left untouched.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d rates synced against %s (as of %s).',
            $result->count,
            $result->base,
            $result->fetchedAt->toDayDateTimeString(),
        ));

        return self::SUCCESS;
    }
}
