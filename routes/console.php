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
