<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('nepse:sync daily')
    ->dailyAt('09:25')
    ->timezone('UTC')
    ->withoutOverlapping();

Schedule::command('nepse:floorsheet-sync')
    ->dailyAt((string) config('nepse.sync.floorsheet_schedule_time', '18:45'))
    ->timezone((string) config('app.timezone'))
    ->withoutOverlapping();
