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

/**
 * Reminds subscribers as their payment falls due and pauses whoever is still
 * unpaid once the grace period ends. Hourly, so each reminder lands within an
 * hour of its threshold — near the time of day the customer last paid — and
 * the pause follows the cutoff closely. Access itself ends exactly on time
 * regardless: Subscription::isActive() checks the grace period on its own.
 */
Schedule::command('subscriptions:check-renewals')
    ->hourly()
    ->withoutOverlapping();

/**
 * Shared hosting has no supervisor, so cron drains the database queue: a
 * worker starts each minute, works until the queue is empty or 50 seconds
 * pass, then exits before the next one is due.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
