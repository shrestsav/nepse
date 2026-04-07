<?php

use App\Models\AggregatedFloorsheet;
use App\Models\PriceHistory;
use App\Models\Sector;
use App\Models\Stock;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

const BROKER_NET_FLOW_STRATEGY_SLUG = 'broker-net-flow-imbalance-momentum';

test('guests are redirected from strategy pages', function () {
    $this->get(route('dashboard.strategies'))->assertRedirect(route('login'));
    $this->get(route('dashboard.strategies.show', ['slug' => BROKER_NET_FLOW_STRATEGY_SLUG]))
        ->assertRedirect(route('login'));
});

test('strategy detail page renders computed rows', function () {
    $sector = Sector::factory()->create(['name' => 'Commercial Bank']);
    $stock = Stock::factory()->for($sector)->create([
        'symbol' => 'AAA',
        'company_name' => 'Alpha Bank',
    ]);

    PriceHistory::factory()->for($stock)->create([
        'date' => '2026-04-01',
        'closing_price' => 500,
        'change_percent' => 2.1,
    ]);

    foreach (range(1, 12) as $index) {
        AggregatedFloorsheet::query()->create([
            'trade_date' => '2026-04-01',
            'symbol' => 'AAA',
            'stock_id' => $stock->id,
            'buyer_broker_no' => $index <= 4 ? '101' : ($index <= 8 ? '102' : '103'),
            'seller_broker_no' => sprintf('2%02d', $index),
            'rate' => 500,
            'transaction_count' => 1,
            'total_quantity' => 100,
            'total_amount' => 1000000,
        ]);
    }

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.strategies.show', [
            'slug' => BROKER_NET_FLOW_STRATEGY_SLUG,
            'date' => '2026-04-01',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/StrategyShow')
            ->where('strategy.slug', BROKER_NET_FLOW_STRATEGY_SLUG)
            ->where('selectedDate', '2026-04-01')
            ->where('summary.symbolsScanned', 1)
            ->where('summary.symbolsPassingTurnover', 1)
            ->has('rows', 1)
            ->where('rows.0.symbol', 'AAA')
            ->where('rows.0.signal', 'buy'),
        );
});

test('unknown strategy slug returns 404', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.strategies.show', ['slug' => 'unknown-strategy']))
        ->assertNotFound();
});

test('strategy detail validates query filters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard.strategies.show', ['slug' => BROKER_NET_FLOW_STRATEGY_SLUG]))
        ->get(route('dashboard.strategies.show', [
            'slug' => BROKER_NET_FLOW_STRATEGY_SLUG,
            'date' => 'bad-date',
        ]))
        ->assertRedirect(route('dashboard.strategies.show', ['slug' => BROKER_NET_FLOW_STRATEGY_SLUG]))
        ->assertSessionHasErrors(['date']);

    $this->actingAs($user)
        ->from(route('dashboard.strategies.show', ['slug' => BROKER_NET_FLOW_STRATEGY_SLUG]))
        ->get(route('dashboard.strategies.show', [
            'slug' => BROKER_NET_FLOW_STRATEGY_SLUG,
            'minTurnover' => -1,
        ]))
        ->assertRedirect(route('dashboard.strategies.show', ['slug' => BROKER_NET_FLOW_STRATEGY_SLUG]))
        ->assertSessionHasErrors(['minTurnover']);

    $this->actingAs($user)
        ->from(route('dashboard.strategies.show', ['slug' => BROKER_NET_FLOW_STRATEGY_SLUG]))
        ->get(route('dashboard.strategies.show', [
            'slug' => BROKER_NET_FLOW_STRATEGY_SLUG,
            'limit' => 101,
        ]))
        ->assertRedirect(route('dashboard.strategies.show', ['slug' => BROKER_NET_FLOW_STRATEGY_SLUG]))
        ->assertSessionHasErrors(['limit']);
});
