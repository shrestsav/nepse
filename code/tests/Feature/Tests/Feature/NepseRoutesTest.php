<?php

use App\Models\User;
use App\Services\Nepse\RecommendationService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

dataset('nepse routes', [
    'dashboard' => 'dashboard',
    'recommendations' => 'dashboard.recommendations',
    'sync' => 'dashboard.sync',
    'stocks' => 'dashboard.stocks',
]);

test('guests are redirected away from nepse routes', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with('nepse routes');

test('authenticated users can view the nepse dashboard', function () {
    $service = \Mockery::mock(RecommendationService::class);
    $service->shouldReceive('summary')->once()->andReturn([
        'counts' => [
            'stocks' => 25,
            'sectors' => 8,
            'priceHistories' => 1_200,
        ],
        'recommendationCounts' => [
            'rsiAdx' => 2,
            'rsiMacd' => 3,
            'maEmaAdx' => 4,
        ],
        'recommendationDate' => '2026-03-21',
        'latestSync' => null,
        'currentSync' => null,
    ]);
    app()->instance(RecommendationService::class, $service);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/Dashboard')
            ->where('counts.stocks', 25)
            ->where('recommendationCounts.maEmaAdx', 4),
        );
});

test('recommendation page receives grouped props', function () {
    $service = \Mockery::mock(RecommendationService::class);
    $selectedDate = CarbonImmutable::parse('2026-03-21');
    $service->shouldReceive('resolveAsOfDate')->once()->with(null)->andReturn($selectedDate);
    $service->shouldReceive('buildRecommendationGroups')->once()->with($selectedDate)->andReturn([
        'rsiAdx' => [
            'buy' => [[
                'symbol' => 'AAA',
                'companyName' => 'Alpha Bank',
                'sector' => 'Commercial Bank',
                'asOfDate' => '2026-03-21',
                'closeOnDate' => 148.75,
                'closeToday' => 150.25,
                'stopLoss' => null,
                'tradedSharePercent' => 42.0,
                'metrics' => [
                    'rsi' => ['recent' => [51, 56, 61], 'series' => [44, 51, 56, 61], 'latest' => 61],
                    'adx' => ['recent' => [24, 28, 33], 'series' => [18, 24, 28, 33], 'latest' => 33],
                ],
                'deltas' => [
                    'rsi' => 5.0,
                    'adx' => 5.0,
                ],
            ]],
            'sell' => [],
        ],
        'rsiMacd' => [
            'buy' => [],
            'sell' => [],
        ],
        'maEmaAdx' => [
            'buy' => [],
            'sell' => [],
        ],
    ]);
    $service->shouldReceive('earliestTradingDate')->once()->andReturn(CarbonImmutable::parse('2025-01-01'));
    $service->shouldReceive('latestTradingDate')->once()->andReturn($selectedDate);
    app()->instance(RecommendationService::class, $service);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.recommendations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/Recommendations')
            ->where('selectedDate', '2026-03-21')
            ->has('groups.rsiAdx.buy', 1)
            ->where('groups.rsiAdx.buy.0.symbol', 'AAA')
            ->where('groups.rsiMacd.buy', [])
            ->where('groups.maEmaAdx.sell', []),
        );
});

test('sync and stocks pages render for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.sync'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/Sync')
            ->has('modes', 2)
            ->where('modes.0.value', 'smart')
            ->where('modes.1.value', 'live'),
        );

    $this->actingAs($user)
        ->get(route('dashboard.stocks'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/Stocks')
            ->has('stocks.data'),
        );
});
