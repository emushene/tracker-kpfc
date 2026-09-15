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
| Vehicle Location Resolution
|--------------------------------------------------------------------------
|
| Resolve human-readable vehicle locations every 10 minutes,
| but only between 06:00 and 18:00.
|
*/

Schedule::command('vehicles:update-locations')
    ->everyTenMinutes()
    ->between('06:00', '18:00')
    ->withoutOverlapping();

