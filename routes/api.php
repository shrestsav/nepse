<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/getAllStocks', [App\Http\Controllers\StockController::class, 'getAllStocks']);

Route::get('/getLastSyncLog', [App\Http\Controllers\SyncLogController::class, 'lastSyncLog']);
Route::post('/createSyncLog', [App\Http\Controllers\SyncLogController::class, 'createSyncLog']);

//Get Price History from NepaliPaisa.com
Route::post('/nepalipaisa/pricehistory', [App\Http\Controllers\NepaliPaisaApiController::class, 'getPriceHistory']);
Route::get('/merolagani/livePrice', [App\Http\Controllers\MeroLaganiController::class, 'livePrice']);

Route::get('/get_recommendations_by_rsi_n_adx', [App\Http\Controllers\TraderController::class, 'getRecommendationsByRsiNAdx']);
Route::get('/get_recommendations_by_rsi_n_macd', [App\Http\Controllers\TraderController::class, 'getRecommendationsByRsiNMacd']);
Route::get('/get_recommendations_by_ma_ema_adx', [App\Http\Controllers\TraderController::class, 'getRecommendationsByMaEmaAdx']);
