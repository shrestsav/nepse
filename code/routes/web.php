<?php

use App\Http\Controllers\Blog\ManageBlogPostController;
use App\Http\Controllers\Blog\PublicBlogController;
use App\Http\Controllers\Nepse\BacktestingController;
use App\Http\Controllers\Nepse\DashboardController;
use App\Http\Controllers\Nepse\FloorsheetController;
use App\Http\Controllers\Nepse\RecommendationController;
use App\Http\Controllers\Nepse\SectorController;
use App\Http\Controllers\Nepse\StockController;
use App\Http\Controllers\Nepse\StrategyController;
use App\Http\Controllers\Nepse\SyncController;
use App\Http\Controllers\Nepse\WatchStockController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicBlogController::class, 'index'])->name('home');
Route::get('blog/{blogPost:slug}', [PublicBlogController::class, 'show'])->name('blog.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/recommendations', [RecommendationController::class, 'index'])->name('dashboard.recommendations');
    Route::get('dashboard/watch-stock', [WatchStockController::class, 'index'])->name('dashboard.watch-stock');
    Route::get('dashboard/watch-stock/quote', [WatchStockController::class, 'quote'])->name('dashboard.watch-stock.quote');
    Route::get('dashboard/backtesting', [BacktestingController::class, 'index'])->name('dashboard.backtesting');
    Route::post('dashboard/backtesting', [BacktestingController::class, 'store'])->name('dashboard.backtesting.store');
    Route::get('dashboard/backtesting/{run}', [BacktestingController::class, 'show'])->name('dashboard.backtesting.show');
    Route::get('dashboard/floorsheet', [FloorsheetController::class, 'index'])->name('dashboard.floorsheet');
    Route::get('dashboard/sync', [SyncController::class, 'index'])->name('dashboard.sync');
    Route::post('dashboard/sync', [SyncController::class, 'store'])->name('dashboard.sync.store');
    Route::get('dashboard/sectors', [SectorController::class, 'index'])->name('dashboard.sectors');
    Route::get('dashboard/strategies', [StrategyController::class, 'index'])->name('dashboard.strategies');
    Route::get('dashboard/strategies/{slug}', [StrategyController::class, 'show'])->name('dashboard.strategies.show');
    Route::get('dashboard/stocks', [StockController::class, 'index'])->name('dashboard.stocks');
    Route::get('dashboard/stocks/{stock}', [StockController::class, 'show'])->name('dashboard.stocks.show');

    Route::prefix('dashboard/blog/posts')
        ->name('dashboard.blog.posts.')
        ->group(function (): void {
            Route::get('/', [ManageBlogPostController::class, 'index'])->name('index');
            Route::get('create', [ManageBlogPostController::class, 'create'])->name('create');
            Route::post('/', [ManageBlogPostController::class, 'store'])->name('store');
            Route::get('{blogPost}/edit', [ManageBlogPostController::class, 'edit'])->name('edit');
            Route::put('{blogPost}', [ManageBlogPostController::class, 'update'])->name('update');
            Route::delete('{blogPost}', [ManageBlogPostController::class, 'destroy'])->name('destroy');
            Route::post('{blogPost}/publish', [ManageBlogPostController::class, 'publish'])->name('publish');
            Route::post('{blogPost}/archive', [ManageBlogPostController::class, 'archive'])->name('archive');
        });
});

require __DIR__.'/settings.php';
