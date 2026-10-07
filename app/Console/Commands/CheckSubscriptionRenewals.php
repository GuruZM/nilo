<?php

namespace App\Console\Commands;

use App\Services\SubscriptionDunning;
use Illuminate\Console\Command;

class CheckSubscriptionRenewals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-renewals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remind subscribers whose payment is due and pause subscriptions left unpaid past the grace period';

    public function __construct(protected SubscriptionDunning $dunning)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $result = $this->dunning->run();

        $this->info($result->summary());

        if ($result->errors > 0) {
            $this->warn('Some subscriptions could not be processed; see the log for details.');
        }

        return self::SUCCESS;
    }
}
