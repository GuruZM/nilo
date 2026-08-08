<?php

namespace App\Console\Commands;

use App\Services\Dpo\DpoReconciler;
use Illuminate\Console\Command;

class ReconcileDpoPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dpo:reconcile
        {--minutes=15 : Only check payments started at least this long ago}
        {--limit=200 : The most payments to check in one run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Settle DPO payments whose customer never made it back from the payment page';

    public function __construct(protected DpoReconciler $reconciler)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $result = $this->reconciler->reconcile(
            olderThanMinutes: (int) $this->option('minutes'),
            limit: (int) $this->option('limit'),
        );

        $this->info($result->summary());

        if ($result->errors > 0) {
            $this->warn('Some payments could not be checked; see the log for details.');
        }

        return self::SUCCESS;
    }
}
