<?php

use App\Models\AggregatedFloorsheet;
use App\Models\Broker;
use App\Models\Floorsheet;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('aggregated floorsheet migration creates expected table columns and indexes', function () {
    expect(Schema::hasTable('aggregated_floorsheet'))->toBeTrue()
        ->and(Schema::hasColumns('aggregated_floorsheet', [
            'id',
            'trade_date',
            'symbol',
            'stock_id',
            'buyer_broker_no',
            'seller_broker_no',
            'buyer_broker_id',
            'seller_broker_id',
            'rate',
            'transaction_count',
            'total_quantity',
            'total_amount',
            'created_at',
            'updated_at',
        ]))->toBeTrue();

    $indexes = collect(DB::select("PRAGMA index_list('aggregated_floorsheet')"))
        ->pluck('name')
        ->all();

    expect($indexes)->toContain(
        'aggregated_floorsheet_trade_date_symbol_idx',
        'aggregated_floorsheet_trade_date_broker_idx',
        'aggregated_floorsheet_group_unique',
    );
});

test('floorsheet aggregate command rebuilds grouped rows for a single date', function () {
    Carbon::setTestNow('2026-04-05 12:00:00');

    [$stock, $buyerBroker, $sellerBroker] = seedAggregateReferenceData();

    Floorsheet::query()->create([
        'transaction' => '2026040501000001',
        'trade_date' => '2026-04-05',
        'symbol' => $stock->symbol,
        'stock_id' => $stock->id,
        'buyer_broker_no' => $buyerBroker->broker_no,
        'seller_broker_no' => $sellerBroker->broker_no,
        'buyer_broker_id' => $buyerBroker->id,
        'seller_broker_id' => $sellerBroker->id,
        'quantity' => 10,
        'rate' => 510,
        'amount' => 5100,
    ]);

    Floorsheet::query()->create([
        'transaction' => '2026040501000002',
        'trade_date' => '2026-04-05',
        'symbol' => $stock->symbol,
        'stock_id' => $stock->id,
        'buyer_broker_no' => $buyerBroker->broker_no,
        'seller_broker_no' => $sellerBroker->broker_no,
        'buyer_broker_id' => $buyerBroker->id,
        'seller_broker_id' => $sellerBroker->id,
        'quantity' => 15,
        'rate' => 510,
        'amount' => 7650,
    ]);

    Floorsheet::query()->create([
        'transaction' => '2026040501000003',
        'trade_date' => '2026-04-05',
        'symbol' => $stock->symbol,
        'stock_id' => $stock->id,
        'buyer_broker_no' => $buyerBroker->broker_no,
        'seller_broker_no' => $sellerBroker->broker_no,
        'buyer_broker_id' => $buyerBroker->id,
        'seller_broker_id' => $sellerBroker->id,
        'quantity' => 20,
        'rate' => 515,
        'amount' => 10300,
    ]);

    Floorsheet::query()->create([
        'transaction' => '2026040501000004',
        'trade_date' => '2026-04-05',
        'symbol' => 'ZZZ',
        'stock_id' => null,
        'buyer_broker_no' => '999',
        'seller_broker_no' => '',
        'buyer_broker_id' => null,
        'seller_broker_id' => null,
        'quantity' => 7,
        'rate' => 99.5,
        'amount' => 696.5,
    ]);

    $this->artisan('nepse:floorsheet-aggregate', [
        '--date' => '2026-04-05',
    ])
        ->expectsOutputToContain('Trade dates processed: 1')
        ->expectsOutputToContain('Date range: 2026-04-05 to 2026-04-05')
        ->expectsOutputToContain('Aggregated rows rebuilt: 3')
        ->assertExitCode(0);

    expect(AggregatedFloorsheet::query()->count())->toBe(3);

    $primaryGroup = AggregatedFloorsheet::query()
        ->whereDate('trade_date', '2026-04-05')
        ->where('symbol', $stock->symbol)
        ->where('rate', 510)
        ->firstOrFail();

    expect($primaryGroup->stock_id)->toBe($stock->id)
        ->and($primaryGroup->buyer_broker_id)->toBe($buyerBroker->id)
        ->and($primaryGroup->seller_broker_id)->toBe($sellerBroker->id)
        ->and($primaryGroup->transaction_count)->toBe(2)
        ->and($primaryGroup->total_quantity)->toBe(25)
        ->and($primaryGroup->total_amount)->toBe(12750.0);

    $unresolvedGroup = AggregatedFloorsheet::query()
        ->where('symbol', 'ZZZ')
        ->firstOrFail();

    expect($unresolvedGroup->stock_id)->toBeNull()
        ->and($unresolvedGroup->buyer_broker_id)->toBeNull()
        ->and($unresolvedGroup->seller_broker_id)->toBeNull()
        ->and($unresolvedGroup->transaction_count)->toBe(1)
        ->and($unresolvedGroup->total_quantity)->toBe(7)
        ->and($unresolvedGroup->total_amount)->toBe(696.5);
});

test('floorsheet aggregate command is idempotent and replaces stale aggregates for a date', function () {
    Carbon::setTestNow('2026-04-05 12:00:00');

    [$stock, $buyerBroker, $sellerBroker] = seedAggregateReferenceData();

    Floorsheet::query()->create([
        'transaction' => '2026040501000010',
        'trade_date' => '2026-04-05',
        'symbol' => $stock->symbol,
        'stock_id' => $stock->id,
        'buyer_broker_no' => $buyerBroker->broker_no,
        'seller_broker_no' => $sellerBroker->broker_no,
        'buyer_broker_id' => $buyerBroker->id,
        'seller_broker_id' => $sellerBroker->id,
        'quantity' => 12,
        'rate' => 500,
        'amount' => 6000,
    ]);

    AggregatedFloorsheet::query()->create([
        'trade_date' => '2026-04-05',
        'symbol' => $stock->symbol,
        'stock_id' => $stock->id,
        'buyer_broker_no' => $buyerBroker->broker_no,
        'seller_broker_no' => $sellerBroker->broker_no,
        'buyer_broker_id' => $buyerBroker->id,
        'seller_broker_id' => $sellerBroker->id,
        'rate' => 500,
        'transaction_count' => 99,
        'total_quantity' => 999,
        'total_amount' => 999999,
    ]);

    $this->artisan('nepse:floorsheet-aggregate', ['--date' => '2026-04-05'])->assertExitCode(0);
    $this->artisan('nepse:floorsheet-aggregate', ['--date' => '2026-04-05'])->assertExitCode(0);

    expect(AggregatedFloorsheet::query()->count())->toBe(1)
        ->and(AggregatedFloorsheet::query()->value('transaction_count'))->toBe(1)
        ->and(AggregatedFloorsheet::query()->value('total_quantity'))->toBe(12)
        ->and(AggregatedFloorsheet::query()->value('total_amount'))->toBe(6000.0)
        ->and(Floorsheet::query()->count())->toBe(1);
});

test('floorsheet aggregate command rebuilds only the requested date range', function () {
    [$stock, $buyerBroker, $sellerBroker] = seedAggregateReferenceData();

    Floorsheet::query()->create([
        'transaction' => '2026040401000001',
        'trade_date' => '2026-04-04',
        'symbol' => $stock->symbol,
        'stock_id' => $stock->id,
        'buyer_broker_no' => $buyerBroker->broker_no,
        'seller_broker_no' => $sellerBroker->broker_no,
        'buyer_broker_id' => $buyerBroker->id,
        'seller_broker_id' => $sellerBroker->id,
        'quantity' => 9,
        'rate' => 450,
        'amount' => 4050,
    ]);

    Floorsheet::query()->create([
        'transaction' => '2026040501000001',
        'trade_date' => '2026-04-05',
        'symbol' => $stock->symbol,
        'stock_id' => $stock->id,
        'buyer_broker_no' => $buyerBroker->broker_no,
        'seller_broker_no' => $sellerBroker->broker_no,
        'buyer_broker_id' => $buyerBroker->id,
        'seller_broker_id' => $sellerBroker->id,
        'quantity' => 10,
        'rate' => 460,
        'amount' => 4600,
    ]);

    Floorsheet::query()->create([
        'transaction' => '2026040601000001',
        'trade_date' => '2026-04-06',
        'symbol' => $stock->symbol,
        'stock_id' => $stock->id,
        'buyer_broker_no' => $buyerBroker->broker_no,
        'seller_broker_no' => $sellerBroker->broker_no,
        'buyer_broker_id' => $buyerBroker->id,
        'seller_broker_id' => $sellerBroker->id,
        'quantity' => 11,
        'rate' => 470,
        'amount' => 5170,
    ]);

    AggregatedFloorsheet::query()->create([
        'trade_date' => '2026-04-06',
        'symbol' => 'STALE',
        'stock_id' => null,
        'buyer_broker_no' => '1',
        'seller_broker_no' => '2',
        'buyer_broker_id' => null,
        'seller_broker_id' => null,
        'rate' => 1,
        'transaction_count' => 1,
        'total_quantity' => 1,
        'total_amount' => 1,
    ]);

    $this->artisan('nepse:floorsheet-aggregate', [
        '--from' => '2026-04-04',
        '--to' => '2026-04-05',
    ])
        ->expectsOutputToContain('Trade dates processed: 2')
        ->expectsOutputToContain('Date range: 2026-04-04 to 2026-04-05')
        ->expectsOutputToContain('Aggregated rows rebuilt: 2')
        ->assertExitCode(0);

    expect(AggregatedFloorsheet::query()->whereDate('trade_date', '2026-04-04')->count())->toBe(1)
        ->and(AggregatedFloorsheet::query()->whereDate('trade_date', '2026-04-05')->count())->toBe(1)
        ->and(AggregatedFloorsheet::query()->whereDate('trade_date', '2026-04-06')->count())->toBe(1)
        ->and(AggregatedFloorsheet::query()->whereDate('trade_date', '2026-04-06')->value('symbol'))->toBe('STALE')
        ->and(Floorsheet::query()->count())->toBe(3);
});

function seedAggregateReferenceData(): array
{
    $stock = Stock::factory()->create([
        'symbol' => 'NABIL',
    ]);

    $buyerBroker = Broker::query()->create([
        'broker_no' => '57',
        'broker_name' => 'Buyer Broker',
    ]);

    $sellerBroker = Broker::query()->create([
        'broker_no' => '80',
        'broker_name' => 'Seller Broker',
    ]);

    return [$stock, $buyerBroker, $sellerBroker];
}
