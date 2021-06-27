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

Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');
Route::get('/dashboard/{any}', [App\Http\Controllers\HomeController::class, 'index'])->where('any', '.*');

Route::get('/scrape', [App\Http\Controllers\NepseScrapingController::class, 'scrape']);
Route::post('/pricehistory', [App\Http\Controllers\NepseScrapingController::class, 'getPriceHistory']);
Route::get('/initialize', [App\Http\Controllers\NepseScrapingController::class, 'initialize']);
Route::get('/getAllStocks', [App\Http\Controllers\NepseScrapingController::class, 'getAllStocks']);
Route::get('/test', [App\Http\Controllers\NepseScrapingController::class, 'test']);
Route::get('/pricehistoryone/{name}', [App\Http\Controllers\NepseScrapingController::class, 'priceHistory']);
Route::get('/getPriceForCurrentDay', [App\Http\Controllers\NepseScrapingController::class, 'getPriceForCurrentDay']);
Route::get('/getLastSyncLog', [App\Http\Controllers\NepseScrapingController::class, 'lastSyncLog']);
Route::post('/createSyncLog', [App\Http\Controllers\NepseScrapingController::class, 'createSyncLog']);

Route::get('/trader', [App\Http\Controllers\TraderController::class, 'calculate']);
Route::get('/test', [App\Http\Controllers\TraderController::class, 'test']);