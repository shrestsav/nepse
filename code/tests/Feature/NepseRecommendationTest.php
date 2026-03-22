<?php

use App\Models\PriceHistory;
use App\Models\Sector;
use App\Models\Stock;
use App\Models\User;
use App\Services\Nepse\TechnicalIndicators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from the recommendations page', function () {
    $this->get('/dashboard/recommendations')->assertRedirect(route('login'));
});

test('recommendations can be viewed for a selected historical date', function () {
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
        'symbol' => 'ABCD',
        'company_name' => 'Alpha Beta Company',
    ]);

    PriceHistory::factory()->for($stock)->create([
        'date' => '2024-01-01',
        'closing_price' => 100,
        'max_price' => 101,
        'min_price' => 99,
        'change_percent' => 1,
    ]);
    PriceHistory::factory()->for($stock)->create([
        'date' => '2024-01-02',
        'closing_price' => 110,
        'max_price' => 112,
        'min_price' => 108,
        'change_percent' => 2,
    ]);
    PriceHistory::factory()->for($stock)->create([
        'date' => '2024-01-03',
        'closing_price' => 120,
        'max_price' => 122,
        'min_price' => 118,
        'change_percent' => 2.5,
        'traded_shares' => 500,
        'total_quantity' => 1_000,
    ]);
    PriceHistory::factory()->for($stock)->create([
        'date' => '2024-01-04',
        'closing_price' => 130,
        'max_price' => 132,
        'min_price' => 128,
        'change_percent' => 1.5,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard/recommendations?date=2024-01-03')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/Recommendations')
            ->where('selectedDate', '2024-01-03')
            ->where('groups.rsiAdx.buy.0.symbol', 'ABCD')
            ->where('groups.rsiAdx.buy.0.closeOnDate', 120)
            ->where('groups.rsiAdx.buy.0.closeToday', 130)
            ->where('groups.rsiAdx.buy.0.tradedSharePercent', 50)
            ->where('groups.rsiMacd.buy.0.symbol', 'ABCD')
        );
});
