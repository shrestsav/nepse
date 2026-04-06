<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Boost\Mcp\Boost as BoostMcp;
use Laravel\Mcp\Facades\Mcp;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (
    config('boost.allow_production', false)
    && ! app()->environment('local')
    && config('app.debug', false) !== true
    && class_exists(BoostMcp::class)
) {
    Artisan::command('boost:mcp', function () {
        Mcp::local('laravel-boost', BoostMcp::class);

        return $this->call('mcp:start', ['handle' => 'laravel-boost']);
    })->purpose('Starts Laravel Boost in non-local environments.');
}

Schedule::command('nepse:sync daily')
    ->dailyAt('09:25')
    ->timezone('UTC')
    ->withoutOverlapping();

Schedule::command('nepse:floorsheet-sync')
    ->dailyAt('11:15')
    ->timezone('UTC')
    ->withoutOverlapping();
