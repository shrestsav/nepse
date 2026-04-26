<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Boost\Mcp\Boost as BoostMcp;
use Laravel\Mcp\Facades\Mcp;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');

// if (
//     config('boost.allow_production', false)
//     && ! app()->environment('local')
//     && config('app.debug', false) !== true
//     && class_exists(BoostMcp::class)
// ) {
//     Artisan::command('boost:mcp', function () {
//         Mcp::local('laravel-boost', BoostMcp::class);
//
//         return $this->call('mcp:start', ['handle' => 'laravel-boost']);
//     })->purpose('Starts Laravel Boost in non-local environments.');
// }

// 09:25 UTC = 03:10 PM Nepal Time (NPT)
// Schedule::command('nepse:sync daily')
//     ->dailyAt('09:25')
//     ->timezone('UTC')
//     ->withoutOverlapping();

// 11:15 UTC = 05:00 PM Nepal Time (NPT)
// Schedule::command('nepse:floorsheet-sync')
//     ->dailyAt('11:15')
//     ->timezone('UTC')
//     ->withoutOverlapping();

// 16:15 UTC = 10:00 PM Nepal Time (NPT)
// Schedule::command('nepse:floorsheet-sync')
//     ->dailyAt('16:15')
//     ->timezone('UTC')
//     ->withoutOverlapping();

// 12:15 UTC = 06:00 PM Nepal Time (NPT)
// Schedule::command('nepse:floorsheet-aggregate')
//     ->dailyAt('12:15')
//     ->timezone('UTC')
//     ->withoutOverlapping();

// 17:15 UTC = 11:00 PM Nepal Time (NPT)
// Schedule::command('nepse:floorsheet-aggregate')
//     ->dailyAt('17:15')
//     ->timezone('UTC')
//     ->withoutOverlapping();
