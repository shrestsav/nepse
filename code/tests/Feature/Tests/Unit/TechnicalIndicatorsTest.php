<?php

use App\Models\PriceHistory;
use App\Models\Sector;
use App\Models\Stock;
use App\Services\Nepse\RecommendationService;
use App\Services\Nepse\TechnicalIndicators;

test('ema remains flat for a constant series', function () {
    $ema = app(TechnicalIndicators::class)->ema([10, 10, 10, 10, 10], 3);

    expect($ema)->toBe([10.0, 10.0, 10.0]);
});

test('rsi reaches 100 for a strictly increasing series', function () {
    $rsi = app(TechnicalIndicators::class)->rsi(range(1, 20), 14);

    expect($rsi)->not->toBeEmpty()
        ->and($rsi[array_key_last($rsi)])->toBe(100.0);
});

test('macd resolves to zero for a constant series', function () {
    $macd = app(TechnicalIndicators::class)->macd(array_fill(0, 40, 100.0));

    expect($macd)->not->toBeEmpty()
        ->and($macd[array_key_last($macd)])->toBe(0.0);
});

test('adx remains zero for a flat market series', function () {
    $adx = app(TechnicalIndicators::class)->adx(
        array_fill(0, 40, 100.0),
        array_fill(0, 40, 100.0),
        array_fill(0, 40, 100.0),
        14,
    );

    expect($adx)->not->toBeEmpty()
        ->and($adx[array_key_last($adx)])->toBe(0.0);
});

test('recommendation service groups qualifying stocks from indicator output', function () {
    $sector = Sector::factory()->create(['name' => 'Commercial Bank']);
    $stock = Stock::factory()->for($sector)->create([
        'symbol' => 'AAA',
        'company_name' => 'Alpha Bank',
    ]);

    foreach (range(0, 79) as $offset) {
        PriceHistory::factory()->for($stock)->create([
            'date' => now()->subDays(80 - $offset)->toDateString(),
            'closing_price' => 100 + $offset,
            'max_price' => 101 + $offset,
            'min_price' => 99 + $offset,
            'change_percent' => 1.25,
        ]);
    }

    $indicators = \Mockery::mock(TechnicalIndicators::class);
    $indicators->shouldReceive('adx')->twice()->andReturn([24, 30], [42, 50]);
    $indicators->shouldReceive('rsi')->twice()->andReturn([55, 63], [58, 66]);
    $indicators->shouldReceive('macd')->once()->andReturn([1.2, 1.8]);
    $indicators->shouldReceive('ema')->times(3)->andReturn([160, 170], [140, 150], [150, 160]);

    $service = new RecommendationService($indicators);
    $groups = $service->buildRecommendationGroups();

    expect($groups['rsiAdx']['buy'])->toHaveCount(1)
        ->and($groups['rsiMacd']['buy'])->toHaveCount(1)
        ->and($groups['maEmaAdx']['buy'])->toHaveCount(1)
        ->and($groups['maEmaAdx']['buy'][0]['symbol'])->toBe('AAA');
});
