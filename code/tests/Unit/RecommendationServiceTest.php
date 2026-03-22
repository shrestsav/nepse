<?php

use App\Models\PriceHistory;
use App\Models\Sector;
use App\Models\Stock;
use App\Services\Nepse\RecommendationService;
use App\Services\Nepse\TechnicalIndicators;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('recommendation service evaluates signals for a selected as-of date', function () {
    config()->set('nepse.recommendations.history_start_date', '2024-01-01');
    config()->set('nepse.recommendations.profiles.rsi_adx.minimum_history_points', 2);
    config()->set('nepse.recommendations.profiles.rsi_macd.minimum_history_points', 2);
    config()->set('nepse.recommendations.profiles.ma_ema_adx.minimum_history_points', 2);
    config()->set('nepse.recommendations.excluded_sector_names', []);

    app()->instance(TechnicalIndicators::class, new class extends TechnicalIndicators
    {
        public function adx(array $high, array $low, array $close, int $period): array
        {
            return [24.1, 28.8];
        }

        public function rsi(array $values, int $period): array
        {
            return [55.2, 61.4];
        }

        public function macd(array $values, int $fastPeriod = 12, int $slowPeriod = 26): array
        {
            return [1.2, 2.8];
        }

        public function ema(array $values, int $period): array
        {
            return [100.0, 101.0];
        }
    });

    $sector = Sector::factory()->create([
        'name' => 'Commercial Bank',
    ]);
    $stock = Stock::factory()->for($sector)->create([
        'symbol' => 'UNIT',
    ]);

    foreach ([
        '2024-01-01' => 100,
        '2024-01-02' => 110,
        '2024-01-03' => 120,
        '2024-01-04' => 130,
    ] as $date => $close) {
        PriceHistory::factory()->for($stock)->create([
            'date' => $date,
            'closing_price' => $close,
            'max_price' => $close + 1,
            'min_price' => $close - 1,
            'change_percent' => 2,
            'traded_shares' => 500,
            'total_quantity' => 1_000,
        ]);
    }

    $groups = app(RecommendationService::class)->buildRecommendationGroups(CarbonImmutable::parse('2024-01-03'));

    expect($groups['rsiAdx']['buy'])->toHaveCount(1);
    expect($groups['rsiAdx']['buy'][0]['symbol'])->toBe('UNIT');
    expect($groups['rsiAdx']['buy'][0]['closeOnDate'])->toBe(120.0);
    expect($groups['rsiAdx']['buy'][0]['closeToday'])->toBe(130.0);
});
