<?php

use App\Enums\SyncMode;
use App\Models\PriceHistory;
use App\Models\Sector;
use App\Models\Stock;
use App\Services\Nepse\MeroLaganiCatalogImporter;
use App\Services\Nepse\MeroLaganiLivePriceSynchronizer;
use App\Services\Nepse\NepaliPaisaHistorySynchronizer;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('catalog importer creates sectors and stocks from merolagani responses', function () {
    Http::fake([
        config('nepse.merolagani.market_url') => Http::response(marketHtml(), 200),
        'https://merolagani.com/CompanyDetail.aspx?symbol=AAA' => Http::response(detailHtml('Alpha Bank', 'Commercial Bank'), 200),
    ]);

    $synced = app(MeroLaganiCatalogImporter::class)->sync();

    expect($synced)->toBe(1);

    $this->assertDatabaseHas('sectors', [
        'name' => 'Commercial Bank',
    ]);

    $this->assertDatabaseHas('stocks', [
        'symbol' => 'AAA',
        'company_name' => 'Alpha Bank',
    ]);
});

test('live price synchronizer upserts todays price history', function () {
    $sector = Sector::factory()->create(['name' => 'Commercial Bank']);
    $stock = Stock::factory()->for($sector)->create([
        'symbol' => 'AAA',
        'company_name' => 'Alpha Bank',
    ]);

    Http::fake([
        config('nepse.merolagani.market_url') => Http::response(marketHtml(), 200),
    ]);

    $updated = app(MeroLaganiLivePriceSynchronizer::class)->sync();

    expect($updated)->toBe(1)
        ->and(PriceHistory::query()->where('stock_id', $stock->id)->whereDate('date', now()->toDateString())->exists())->toBeTrue();
});

test('history synchronizer stores nepalipaisa history rows for full sync', function () {
    $stock = Stock::factory()->create(['symbol' => 'AAA']);

    Http::fake([
        config('nepse.nepalipaisa.history_url') => Http::response([
            'd' => [[
                'AsOfDateShortString' => '2026-03-21',
                'ClosingPrice' => '155.50',
                'MaxPrice' => '160.00',
                'MinPrice' => '150.00',
                'Difference' => '5.25',
                'PercentDifference' => '3.49',
                'PreviousClosing' => '150.25',
                'TradedShares' => '12345',
                'TradedAmount' => '987654',
                'TotalQuantity' => '12,345',
                'TotalTransaction' => '456',
                'TotalAmount' => '1,234,567.00',
                'NoOfTransaction' => '89',
            ]],
        ], 200),
    ]);

    $syncedRows = app(NepaliPaisaHistorySynchronizer::class)->sync($stock, SyncMode::Full);

    expect($syncedRows)->toBe(1);

    Http::assertSent(fn (HttpRequest $request): bool => $request['StockSymbol'] === 'AAA'
        && $request['FromDate'] === config('nepse.sync.full_from_date'));

    expect(
        PriceHistory::query()
            ->where('stock_id', $stock->id)
            ->whereDate('date', '2026-03-21')
            ->exists(),
    )->toBeTrue();
});

test('history synchronizer starts smart sync after the latest stored date', function () {
    $stock = Stock::factory()->create(['symbol' => 'AAA']);

    PriceHistory::factory()->for($stock)->create([
        'date' => '2026-03-20',
    ]);

    Http::fake([
        config('nepse.nepalipaisa.history_url') => Http::response(['d' => []], 200),
    ]);

    app(NepaliPaisaHistorySynchronizer::class)->sync($stock, SyncMode::Smart);

    Http::assertSent(fn (HttpRequest $request): bool => $request['StockSymbol'] === 'AAA'
        && $request['FromDate'] === '2026-03-21');
});

function marketHtml(): string
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
                    <td><a href="/CompanyDetail.aspx?symbol=AAA">AAA</a></td>
                    <td>155.50</td>
                    <td>3.49%</td>
                    <td>160.00</td>
                    <td>150.00</td>
                    <td>151.00</td>
                    <td>12,345</td>
                </tr>
            </tbody>
        </table>
    </body>
</html>
HTML;
}

function detailHtml(string $companyName, string $sector): string
{
    return <<<HTML
<html>
    <body>
        <table>
            <tbody>
                <tr><th>Company Name</th><td>{$companyName}</td></tr>
                <tr><th>Sector</th><td>{$sector}</td></tr>
            </tbody>
        </table>
    </body>
</html>
HTML;
}
