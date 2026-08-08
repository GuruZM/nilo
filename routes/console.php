<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Upstream only refreshes once every 24 hours, so polling harder buys nothing.
 */
Schedule::command('exchange-rates:sync')
    ->dailyAt('01:00')
    ->withoutOverlapping();

/**
 * Catches subscribers who paid but closed the tab before DPO could redirect
 * them back. DPO holds a transaction for 24 hours, so a quarter-hourly sweep
 * settles every one of them well inside its own lifetime.
 */
Schedule::command('dpo:reconcile')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
