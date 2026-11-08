<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use App\Jobs\SyncPosAvailabilityJob;
use App\Jobs\CheckPosConnectionHealthJob;

// Periodic stock availability refresh every 10 minutes
Schedule::job(new SyncPosAvailabilityJob)->everyTenMinutes();

// Periodic connection health check every 30 minutes
Schedule::job(new CheckPosConnectionHealthJob)->everyThirtyMinutes();

// Periodic release of abandoned checkouts and restoration of stock every 5 minutes
Schedule::command('orders:expire-abandoned')->everyFiveMinutes();

// Periodic sweep to retry unconfirmed POS reservations every 15 minutes
Schedule::command('orders:retry-pos-confirmations')->everyFifteenMinutes();

// Periodic sweep to release mature affiliate commissions into wallets
Schedule::command('affiliates:release-commissions')->hourly();

// Automated daily MySQL database backup at 02:00 AM (keeps 30 days history)
Schedule::command('app:database-back-up')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/backup.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Automated database backup scheduled run failed at 02:00 AM.');
    });

// Automatically process queued background jobs (order emails, alerts) every minute
Schedule::command('queue:work --stop-when-empty --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
