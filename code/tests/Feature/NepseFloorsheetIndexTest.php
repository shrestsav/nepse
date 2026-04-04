<?php

use App\Models\Broker;
use App\Models\Floorsheet;
use App\Models\Sector;
use App\Models\Stock;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('floorsheet index shows daily rows with available broker filters', function () {
    $sector = Sector::factory()->create(['name' => 'Commercial Bank']);
    $stock = Stock::factory()->for($sector)->create([
        'symbol' => 'NABIL',
        'company_name' => 'Nabil Bank Limited',
    ]);

    $buyer = Broker::query()->create([
        'broker_no' => '45',
        'broker_name' => 'ABC Securities',
    ]);

    $seller = Broker::query()->create([
        'broker_no' => '32',
        'broker_name' => 'XYZ Securities',
    ]);

    Floorsheet::query()->create([
        'transaction' => '2026032601000001',
        'trade_date' => '2026-03-26',
        'symbol' => 'NABIL',
        'stock_id' => $stock->id,
        'buyer_broker_no' => $buyer->broker_no,
        'seller_broker_no' => $seller->broker_no,
        'buyer_broker_id' => $buyer->id,
        'seller_broker_id' => $seller->id,
        'quantity' => 50,
        'rate' => 542,
        'amount' => 27100,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.floorsheet'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/Floorsheet')
            ->where('filters.date', '2026-03-26')
            ->where('filters.quantityRange', 'all')
            ->where('summary.matchingRows', 1)
            ->has('floorsheets.data', 1)
            ->where('floorsheets.data.0.symbol', 'NABIL')
            ->where('floorsheets.data.0.buyerBrokerNo', '45')
            ->has('brokers', 2)
            ->where('dateBounds.min', '2026-03-26')
            ->where('dateBounds.max', '2026-03-26'),
        );
});

test('floorsheet index filters by date symbol broker and quantity range', function () {
    $sector = Sector::factory()->create(['name' => 'Hydro Power']);
    $stock = Stock::factory()->for($sector)->create([
        'symbol' => 'UPPER',
        'company_name' => 'Upper Tamakoshi Hydropower Limited',
    ]);

    $buyer45 = Broker::query()->create([
        'broker_no' => '45',
        'broker_name' => 'ABC Securities',
    ]);

    $seller32 = Broker::query()->create([
        'broker_no' => '32',
        'broker_name' => 'XYZ Securities',
    ]);

    $seller99 = Broker::query()->create([
        'broker_no' => '99',
        'broker_name' => 'LMN Securities',
    ]);

    Floorsheet::query()->create([
        'transaction' => '2026032601000001',
        'trade_date' => '2026-03-26',
        'symbol' => 'UPPER',
        'stock_id' => $stock->id,
        'buyer_broker_no' => $buyer45->broker_no,
        'seller_broker_no' => $seller32->broker_no,
        'buyer_broker_id' => $buyer45->id,
        'seller_broker_id' => $seller32->id,
        'quantity' => 80,
        'rate' => 500,
        'amount' => 40000,
    ]);

    Floorsheet::query()->create([
        'transaction' => '2026032601000002',
        'trade_date' => '2026-03-26',
        'symbol' => 'NABIL',
        'stock_id' => null,
        'buyer_broker_no' => $buyer45->broker_no,
        'seller_broker_no' => $seller99->broker_no,
        'buyer_broker_id' => $buyer45->id,
        'seller_broker_id' => $seller99->id,
        'quantity' => 5,
        'rate' => 800,
        'amount' => 4000,
    ]);

    Floorsheet::query()->create([
        'transaction' => '2026032701000003',
        'trade_date' => '2026-03-27',
        'symbol' => 'UPPER',
        'stock_id' => $stock->id,
        'buyer_broker_no' => $buyer45->broker_no,
        'seller_broker_no' => $seller32->broker_no,
        'buyer_broker_id' => $buyer45->id,
        'seller_broker_id' => $seller32->id,
        'quantity' => 120,
        'rate' => 501,
        'amount' => 60120,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.floorsheet', [
            'date' => '2026-03-26',
            'symbol' => 'upper',
            'buyer' => '45',
            'seller' => '32',
            'quantityRange' => '10-100',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/Floorsheet')
            ->where('filters.date', '2026-03-26')
            ->where('filters.symbol', 'UPPER')
            ->where('filters.buyer', '45')
            ->where('filters.seller', '32')
            ->where('filters.quantityRange', '10-100')
            ->where('summary.matchingRows', 1)
            ->has('floorsheets.data', 1)
            ->where('floorsheets.data.0.transaction', '2026032601000001'),
        );
});
