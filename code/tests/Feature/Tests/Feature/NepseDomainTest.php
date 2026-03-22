<?php

use App\Enums\SyncMode;
use App\Enums\SyncStatus;
use App\Models\PriceHistory;
use App\Models\Sector;
use App\Models\Stock;
use App\Models\SyncLog;
use Illuminate\Database\QueryException;

test('stock relationships and hlc3 accessor work', function () {
    $sector = Sector::factory()->create(['name' => 'Commercial Bank']);
    $stock = Stock::factory()->for($sector)->create(['symbol' => 'AAA']);
    $olderHistory = PriceHistory::factory()->for($stock)->create([
        'date' => '2026-03-20',
        'closing_price' => 120,
        'max_price' => 128,
        'min_price' => 116,
    ]);
    $latestHistory = PriceHistory::factory()->for($stock)->create([
        'date' => '2026-03-21',
        'closing_price' => 132,
        'max_price' => 140,
        'min_price' => 128,
    ]);

    expect($stock->fresh()->sector->is($sector))->toBeTrue()
        ->and($stock->fresh()->priceHistories->first()->is($latestHistory))->toBeTrue()
        ->and($stock->fresh()->latestPriceHistory->is($latestHistory))->toBeTrue()
        ->and($olderHistory->hlc3)->toBe(121.3333);
});

test('price history rows are unique per stock and date', function () {
    $stock = Stock::factory()->create();

    PriceHistory::factory()->for($stock)->create([
        'date' => '2026-03-21',
    ]);

    expect(fn () => PriceHistory::factory()->for($stock)->create([
        'date' => '2026-03-21',
    ]))->toThrow(QueryException::class);
});

test('sync logs cast sync mode and status enums', function () {
    $syncLog = SyncLog::factory()->create([
        'type' => SyncMode::Smart,
        'status' => SyncStatus::CompletedWithErrors,
    ]);

    expect($syncLog->fresh()->type)->toBe(SyncMode::Smart)
        ->and($syncLog->fresh()->status)->toBe(SyncStatus::CompletedWithErrors)
        ->and($syncLog->fresh()->isRunning())->toBeFalse();
});
