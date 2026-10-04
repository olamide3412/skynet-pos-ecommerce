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

