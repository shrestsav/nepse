<?php

use App\Models\AggregatedFloorsheet;
use App\Models\PriceHistory;
use App\Models\Sector;
use App\Models\Stock;
use App\Services\Nepse\BrokerNetFlowImbalanceStrategyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedStrategySymbolRows(
    string $symbol,
    int $stockId,
    string $tradeDate,
    array $buyerBrokerNumbers,
    array $sellerBrokerNumbers,
    int $amountPerRow,
): void {
    foreach ($buyerBrokerNumbers as $index => $buyerBrokerNo) {
        AggregatedFloorsheet::query()->create([
            'trade_date' => $tradeDate,
            'symbol' => $symbol,
            'stock_id' => $stockId,
            'buyer_broker_no' => $buyerBrokerNo,
            'seller_broker_no' => $sellerBrokerNumbers[$index],
            'rate' => 100,
            'transaction_count' => 1,
            'total_quantity' => 100,
            'total_amount' => $amountPerRow,
        ]);
    }
}

test('broker net flow strategy computes deterministic buy sell neutral outcomes', function () {
    $sector = Sector::factory()->create(['name' => 'Commercial Bank']);
    $tradeDate = '2026-04-01';

    $stocks = collect(['AAA', 'BBB', 'CCC', 'DDD'])->mapWithKeys(function (string $symbol) use ($sector, $tradeDate): array {
        $stock = Stock::factory()->for($sector)->create([
            'symbol' => $symbol,
            'company_name' => "{$symbol} Company",
        ]);

        PriceHistory::factory()->for($stock)->create([
            'date' => $tradeDate,
            'closing_price' => 500,
            'change_percent' => match ($symbol) {
                'AAA' => 2.0,
                'BBB' => -1.5,
                'CCC' => 1.2,
                default => 0.6,
            },
        ]);

        return [$symbol => $stock];
    });

    seedStrategySymbolRows(
        'AAA',
        $stocks['AAA']->id,
        $tradeDate,
        ['101', '101', '101', '101', '102', '102', '102', '102', '103', '103', '103', '103'],
        ['201', '202', '203', '204', '205', '206', '207', '208', '209', '210', '211', '212'],
        1000000,
    );

    seedStrategySymbolRows(
        'BBB',
        $stocks['BBB']->id,
        $tradeDate,
        ['301', '302', '303', '304', '305', '306', '307', '308', '309', '310', '311', '312'],
        ['401', '401', '401', '401', '402', '402', '402', '402', '403', '403', '403', '403'],
        1000000,
    );

    seedStrategySymbolRows(
        'CCC',
        $stocks['CCC']->id,
        $tradeDate,
        ['501', '501', '501', '502', '502', '502'],
        ['601', '602', '603', '604', '605', '606'],
        1000000,
    );

    seedStrategySymbolRows(
        'DDD',
        $stocks['DDD']->id,
        $tradeDate,
        ['701', '701', '702', '702'],
        ['801', '802', '803', '804'],
        1000000,
    );

    $analysis = app(BrokerNetFlowImbalanceStrategyService::class)->analyze(
        CarbonImmutable::parse($tradeDate),
        5000000,
        25,
        0.15,
        0.45,
    );

    expect($analysis['summary'])->toBe([
        'symbolsScanned' => 4,
        'symbolsPassingTurnover' => 3,
        'buyCandidates' => 1,
        'sellCandidates' => 1,
        'neutral' => 1,
    ]);

    $rowsBySymbol = collect($analysis['rows'])->keyBy('symbol');

    expect($rowsBySymbol['AAA']['signal'])->toBe('buy');
    expect($rowsBySymbol['AAA']['turnover'])->toBe(12000000.0);
    expect($rowsBySymbol['AAA']['netFlowTop5'])->toBe(10000000.0);
    expect($rowsBySymbol['AAA']['netFlowRatio'])->toBe(0.8333);
    expect($rowsBySymbol['AAA']['dominanceRatio'])->toBe(0.3333);

    expect($rowsBySymbol['BBB']['signal'])->toBe('sell');
    expect($rowsBySymbol['BBB']['netFlowTop5'])->toBe(-10000000.0);
    expect($rowsBySymbol['BBB']['netFlowRatio'])->toBe(-0.8333);
    expect($rowsBySymbol['BBB']['dominanceRatio'])->toBe(0.3333);

    expect($rowsBySymbol['CCC']['signal'])->toBe('neutral');
    expect($rowsBySymbol['CCC']['netFlowTop5'])->toBe(3000000.0);
    expect($rowsBySymbol['CCC']['netFlowRatio'])->toBe(0.5);
    expect($rowsBySymbol['CCC']['dominanceRatio'])->toBe(0.5);
});

test('broker net flow strategy applies turnover threshold and limit', function () {
    $sector = Sector::factory()->create(['name' => 'Commercial Bank']);
    $tradeDate = '2026-04-01';

    $stock = Stock::factory()->for($sector)->create([
        'symbol' => 'AAA',
        'company_name' => 'AAA Company',
    ]);

    PriceHistory::factory()->for($stock)->create([
        'date' => $tradeDate,
        'closing_price' => 500,
        'change_percent' => 2.0,
    ]);

    seedStrategySymbolRows(
        'AAA',
        $stock->id,
        $tradeDate,
        ['101', '101', '101', '101', '102', '102', '102', '102', '103', '103', '103', '103'],
        ['201', '202', '203', '204', '205', '206', '207', '208', '209', '210', '211', '212'],
        1000000,
    );

    $service = app(BrokerNetFlowImbalanceStrategyService::class);

    $limited = $service->analyze(
        CarbonImmutable::parse($tradeDate),
        5000000,
        1,
        0.15,
        0.45,
    );

    expect($limited['rows'])->toHaveCount(1);
    expect($limited['rows'][0]['symbol'])->toBe('AAA');

    $filteredOut = $service->analyze(
        CarbonImmutable::parse($tradeDate),
        13000000,
        25,
        0.15,
        0.45,
    );

    expect($filteredOut['summary']['symbolsScanned'])->toBe(1);
    expect($filteredOut['summary']['symbolsPassingTurnover'])->toBe(0);
    expect($filteredOut['rows'])->toBe([]);
});
