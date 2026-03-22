<?php

use App\Models\PriceHistory;
use App\Models\Stock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected away from stock history pages', function () {
    $stock = Stock::factory()->create();

    $this->get(route('dashboard.stocks.show', $stock))
        ->assertRedirect(route('login'));
});

test('stock history page shows the latest 100 records in descending order', function () {
    $stock = Stock::factory()->create([
        'symbol' => 'NABIL',
        'company_name' => 'Nabil Bank Limited',
    ]);

    $startDate = CarbonImmutable::parse('2025-01-01');

    foreach (range(0, 119) as $offset) {
        $date = $startDate->addDays($offset);
        $close = 300 + $offset;

        PriceHistory::factory()
            ->for($stock)
            ->create([
                'date' => $date->toDateString(),
                'closing_price' => $close,
                'max_price' => $close + 5,
                'min_price' => $close - 5,
                'change' => 1.25,
                'change_percent' => 0.42,
                'previous_closing' => $close - 1.25,
                'traded_shares' => 1_000 + $offset,
                'no_of_transactions' => 20 + $offset,
                'total_amount' => 100_000 + ($offset * 10),
            ]);
    }

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.stocks.show', $stock))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/StockShow')
            ->where('stock.symbol', 'NABIL')
            ->where('filters.from', null)
            ->where('filters.to', null)
            ->has('priceHistories', 100)
            ->where('priceHistories.0.date', '2025-04-30')
            ->where('priceHistories.99.date', '2025-01-21')
            ->where('rangeSummary.matchingRecords', 120)
            ->where('rangeSummary.shownRecords', 100)
            ->where('rangeSummary.firstDate', '2025-01-01')
            ->where('rangeSummary.lastDate', '2025-04-30'),
        );
});

test('stock history page filters by date range and recalculates the price range summary', function () {
    $stock = Stock::factory()->create([
        'symbol' => 'ADBL',
        'company_name' => 'Agricultural Development Bank Limited',
    ]);

    $rows = [
        ['date' => '2025-02-01', 'close' => 400.0, 'high' => 408.0, 'low' => 395.0],
        ['date' => '2025-02-02', 'close' => 410.0, 'high' => 418.0, 'low' => 403.0],
        ['date' => '2025-02-03', 'close' => 425.0, 'high' => 430.0, 'low' => 420.0],
        ['date' => '2025-02-04', 'close' => 415.0, 'high' => 420.0, 'low' => 410.0],
        ['date' => '2025-02-05', 'close' => 440.0, 'high' => 452.0, 'low' => 432.0],
        ['date' => '2025-02-06', 'close' => 450.0, 'high' => 460.0, 'low' => 445.0],
        ['date' => '2025-02-07', 'close' => 438.0, 'high' => 444.0, 'low' => 430.0],
    ];

    foreach ($rows as $index => $row) {
        PriceHistory::factory()
            ->for($stock)
            ->create([
                'date' => $row['date'],
                'closing_price' => $row['close'],
                'max_price' => $row['high'],
                'min_price' => $row['low'],
                'change' => $index + 1,
                'change_percent' => 1.5,
                'previous_closing' => $row['close'] - 2,
                'traded_shares' => 5_000 + $index,
                'no_of_transactions' => 50 + $index,
                'total_amount' => 500_000 + ($index * 100),
            ]);
    }

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.stocks.show', [
            'stock' => $stock,
            'from' => '2025-02-03',
            'to' => '2025-02-06',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/StockShow')
            ->where('filters.from', '2025-02-03')
            ->where('filters.to', '2025-02-06')
            ->has('priceHistories', 4)
            ->where('priceHistories.0.date', '2025-02-06')
            ->where('priceHistories.1.date', '2025-02-05')
            ->where('priceHistories.3.date', '2025-02-03')
            ->where('rangeSummary.matchingRecords', 4)
            ->where('rangeSummary.shownRecords', 4)
            ->where('rangeSummary.firstDate', '2025-02-03')
            ->where('rangeSummary.lastDate', '2025-02-06')
            ->where('rangeSummary.lowPrice', 410)
            ->where('rangeSummary.highPrice', 460)
            ->where('rangeSummary.earliestClose', 425)
            ->where('rangeSummary.latestClose', 450)
            ->where('rangeSummary.closeChange', 25)
            ->where('rangeSummary.closeChangePercent', 5.88),
        );
});
