<?php

use App\Models\PriceHistory;
use App\Models\Sector;
use App\Models\Stock;
use App\Models\User;
use App\Services\Nepse\MeroLaganiLivePriceSynchronizer;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('watch stock page lists tracked stocks', function () {
    $sector = Sector::factory()->create(['name' => 'Commercial Bank']);
    $stock = Stock::factory()->for($sector)->create([
        'symbol' => 'NABIL',
        'company_name' => 'Nabil Bank Limited',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.watch-stock'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/WatchStock')
            ->has('stocks', 1)
            ->where('stocks.0.id', $stock->id)
            ->where('stocks.0.symbol', 'NABIL')
            ->where('stocks.0.companyName', 'Nabil Bank Limited')
            ->where('stocks.0.sector', 'Commercial Bank'),
        );
});

test('watch stock quote endpoint validates the stock parameter', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('dashboard.watch-stock.quote'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('stock');
});

test('watch stock quote endpoint returns the normalized payload for a valid stock', function () {
    $stock = Stock::factory()->for(Sector::factory()->create())->create([
        'symbol' => 'NABIL',
        'company_name' => 'Nabil Bank Limited',
    ]);

    $service = \Mockery::mock(MeroLaganiLivePriceSynchronizer::class);
    $service->shouldReceive('syncStock')
        ->once()
        ->with(\Mockery::on(fn (Stock $candidate): bool => $candidate->is($stock)))
        ->andReturn([
            'stockId' => $stock->id,
            'symbol' => 'NABIL',
            'companyName' => 'Nabil Bank Limited',
            'sector' => $stock->sector?->name,
            'marketDate' => '2026-03-22',
            'recordedAt' => '2026-03-22T12:00:00+05:45',
            'latestSyncedAt' => '2026-03-22T12:00:02+05:45',
            'price' => 810.25,
            'change' => 10.25,
            'changePercent' => 1.28,
            'previousClose' => 800.00,
            'high' => 815.00,
            'low' => 798.50,
            'open' => 803.00,
            'volume' => 12_345,
        ]);
    app()->instance(MeroLaganiLivePriceSynchronizer::class, $service);

    $this->actingAs(User::factory()->create())
        ->getJson(route('dashboard.watch-stock.quote', ['stock' => $stock]))
        ->assertOk()
        ->assertJsonPath('quote.stockId', $stock->id)
        ->assertJsonPath('quote.symbol', 'NABIL')
        ->assertJsonPath('quote.price', 810.25)
        ->assertJsonPath('quote.latestSyncedAt', '2026-03-22T12:00:02+05:45');
});

test('single stock live sync upserts todays row and returns quote data', function () {
    $sector = Sector::factory()->create(['name' => 'Commercial Bank']);
    $stock = Stock::factory()->for($sector)->create([
        'symbol' => 'NABIL',
        'company_name' => 'Nabil Bank Limited',
    ]);

    PriceHistory::factory()->for($stock)->create([
        'date' => now()->subDay()->toDateString(),
        'closing_price' => 800.00,
        'previous_closing' => 790.00,
    ]);

    Http::fake([
        config('nepse.merolagani.market_url') => Http::response(watchStockMarketHtml(), 200),
    ]);

    $quote = app(MeroLaganiLivePriceSynchronizer::class)->syncStock($stock);

    expect($quote)
        ->toMatchArray([
            'stockId' => $stock->id,
            'symbol' => 'NABIL',
            'companyName' => 'Nabil Bank Limited',
            'sector' => 'Commercial Bank',
            'marketDate' => now()->toDateString(),
            'price' => 810.25,
            'change' => 10.25,
            'changePercent' => 1.28,
            'previousClose' => 800.00,
            'high' => 815.00,
            'low' => 805.50,
            'open' => 806.00,
            'volume' => 12345,
        ]);

    $this->assertDatabaseHas('price_histories', [
        'stock_id' => $stock->id,
        'date' => now()->toDateString(),
        'closing_price' => 810.25,
        'previous_closing' => 800.00,
        'change' => 10.25,
        'change_percent' => 1.28,
        'traded_shares' => 12345,
    ]);
});

function watchStockMarketHtml(): string
{
    return <<<'HTML'
<html>
    <body>
        <table class="table table-hover live-trading sortable">
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>LTP</th>
                    <th>Change %</th>
                    <th>High</th>
                    <th>Low</th>
                    <th>Open</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><a href="/CompanyDetail.aspx?symbol=NABIL">NABIL</a></td>
                    <td>810.25</td>
                    <td>1.28%</td>
                    <td>815.00</td>
                    <td>805.50</td>
                    <td>806.00</td>
                    <td>12,345</td>
                </tr>
            </tbody>
        </table>
    </body>
</html>
HTML;
}
