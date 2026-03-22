<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('nepse:sync daily')
    ->dailyAt((string) config('nepse.sync.daily_schedule_time', '18:15'))
    ->timezone((string) config('app.timezone'))
    ->withoutOverlapping();
