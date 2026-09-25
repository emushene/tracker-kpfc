<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Protrack Position Synchronization
|--------------------------------------------------------------------------
|
| Synchronize vehicle GPS positions every 30 seconds.
| This continues 24 hours a day.
|
*/

Schedule::command('protrack:sync-positions')
    ->everyThirtySeconds()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Protrack Location + Google Sheets Synchronization
|--------------------------------------------------------------------------
|
| Every 10 minutes between 06:00 and 18:00:
|
| 1. Resolve human-readable vehicle locations
| 2. Update the existing Google Sheet
|
*/

Schedule::command('protrack:refresh-locations')
    ->everyTenMinutes()
    ->between('06:00', '18:00')
    ->withoutOverlapping();
