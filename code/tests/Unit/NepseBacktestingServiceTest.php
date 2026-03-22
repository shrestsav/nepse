<?php

use App\Enums\BacktestRunStatus;
use App\Enums\BacktestStrategy;
use App\Models\BacktestRun;
use App\Models\PriceHistory;
use App\Models\Sector;
use App\Models\Stock;
use App\Services\Nepse\BacktestingService;
use App\Services\Nepse\TechnicalIndicators;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedStockHistories(array $prices): Stock
{
    $sector = Sector::factory()->create([
        'name' => 'Commercial Bank',
    ]);
    $stock = Stock::factory()->for($sector)->create([
        'symbol' => 'BTST',
        'company_name' => 'Backtest Stock',
    ]);

    foreach ($prices as $date => $close) {
        PriceHistory::factory()->for($stock)->create([
            'date' => $date,
            'closing_price' => $close,
            'max_price' => $close + 1,
            'min_price' => $close - 1,
            'change_percent' => 2,
        ]);
    }

    return $stock;
}

test('rsi adx backtests create a trade and force close it at run end', function () {
    config()->set('nepse.recommendations.excluded_sector_names', []);
    config()->set('nepse.backtesting.warmup_points', 0);
    config()->set('nepse.backtesting.minimum_hold_days', 3);
    config()->set('nepse.backtesting.profiles.rsi_adx.adx_period', 1);
    config()->set('nepse.backtesting.profiles.rsi_adx.rsi_period', 1);

    seedStockHistories([
        '2024-01-01' => 100,
        '2024-01-02' => 105,
        '2024-01-03' => 110,
        '2024-01-04' => 115,
    ]);

    app()->instance(TechnicalIndicators::class, new class extends TechnicalIndicators
    {
        public function adx(array $high, array $low, array $close, int $period): array
        {
            return [20.0, 24.0, 26.0];
        }

        public function rsi(array $values, int $period): array
        {
            return [55.0, 57.0, 59.0];
        }
    });

    $run = BacktestRun::factory()->create([
        'strategy' => BacktestStrategy::RsiAdx,
        'status' => BacktestRunStatus::Queued,
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-04',
        'started_at' => null,
        'finished_at' => null,
    ]);

    app(BacktestingService::class)->run($run);
    $trade = $run->fresh()->trades()->first();

    expect($run->fresh()->status)->toBe(BacktestRunStatus::Completed);
    expect($trade)->not()->toBeNull();
    expect($trade?->exit_reason)->toBe('forced_close');
    expect($trade?->sell_date?->toDateString())->toBe('2024-01-04');
});

test('ma ema adx backtests honor the minimum hold period before stop loss exits', function () {
    config()->set('nepse.recommendations.excluded_sector_names', []);
    config()->set('nepse.backtesting.warmup_points', 0);
    config()->set('nepse.backtesting.minimum_hold_days', 3);
    config()->set('nepse.backtesting.profiles.ma_ema_adx.adx_period', 1);
    config()->set('nepse.backtesting.profiles.ma_ema_adx.ema_period', 1);
    config()->set('nepse.backtesting.profiles.ma_ema_adx.adx_rise_min', 0);
    config()->set('nepse.backtesting.profiles.ma_ema_adx.price_above_ema_high_min_ratio', 0);

    seedStockHistories([
        '2024-01-01' => 100,
        '2024-01-02' => 105,
        '2024-01-03' => 110,
        '2024-01-04' => 115,
        '2024-01-05' => 120,
        '2024-01-06' => 90,
    ]);

    app()->instance(TechnicalIndicators::class, new class extends TechnicalIndicators
    {
        private int $emaCall = 0;

        public function adx(array $high, array $low, array $close, int $period): array
        {
            return [30.0, 45.0, 50.0, 55.0, 60.0];
        }

        public function ema(array $values, int $period): array
        {
            $this->emaCall++;

            if ($this->emaCall === 1) {
                return [90.0, 95.0, 100.0, 100.0, 100.0, 100.0];
            }

            if ($this->emaCall === 2) {
                return [80.0, 82.0, 85.0, 90.0, 95.0, 95.0];
            }

            return [85.0, 88.0, 92.0, 94.0, 96.0, 96.0];
        }
    });

    $run = BacktestRun::factory()->create([
        'strategy' => BacktestStrategy::MaEmaAdx,
        'status' => BacktestRunStatus::Queued,
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-06',
        'started_at' => null,
        'finished_at' => null,
    ]);

    app(BacktestingService::class)->run($run);
    $trade = $run->fresh()->trades()->first();

    expect($trade)->not()->toBeNull();
    expect($trade?->exit_reason)->toBe('stop_loss');
    expect($trade?->sell_date?->toDateString())->toBe('2024-01-06');
    expect($trade?->holding_days)->toBeGreaterThanOrEqual(3);
});
