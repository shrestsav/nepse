<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return phpinfo();
});
Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');
Route::get('/dashboard/{any}', [App\Http\Controllers\HomeController::class, 'index'])->where('any', '.*');

Route::get('/scrape', [App\Http\Controllers\NepseScrapingController::class, 'scrape']);
Route::post('/pricehistory', [App\Http\Controllers\NepseScrapingController::class, 'getPriceHistory']);
Route::get('/initialize', [App\Http\Controllers\NepseScrapingController::class, 'initialize']);
Route::get('/pricehistoryone/{name}', [App\Http\Controllers\NepseScrapingController::class, 'priceHistory']);
Route::get('/getPriceForCurrentDay', [App\Http\Controllers\NepseScrapingController::class, 'getPriceForCurrentDay']);

Route::get('/dailyPrice', [App\Http\Controllers\MeroLaganiController::class, 'dailyPrice']);

Route::get('/trader', [App\Http\Controllers\TraderController::class, 'getRecommendationsByMaEmaAdx']);

Route::get('/testho', [App\Http\Controllers\NepaliPaisaApiController::class, 'testho']);
