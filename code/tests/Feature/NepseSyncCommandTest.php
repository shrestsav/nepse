<?php

use App\Enums\SyncMode;
use App\Enums\SyncStatus;
use App\Models\PriceHistory;
use App\Models\Stock;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

test('daily sync command imports the last configured week of prices synchronously', function () {
    Carbon::setTestNow('2026-03-22 12:00:00');
    config()->set('nepse.sync.daily_lookback_days', 7);

    Http::fake(function (HttpRequest $request) {
        if ($request->url() === config('nepse.merolagani.market_url')) {
            return Http::response(commandMarketHtml(), 200);
        }

        if ($request->url() === 'https://merolagani.com/CompanyDetail.aspx?symbol=AAA') {
            return Http::response(commandDetailHtml('Alpha Bank', 'Commercial Bank'), 200);
        }

        if (str_starts_with($request->url(), config('nepse.nepalipaisa.daily_price_url'))) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return Http::response(dailyPriceResponse('AAA', (string) ($query['tradeDate'] ?? '2026-03-22')), 200);
        }

        return Http::response([], 500);
    });

    $this->artisan('nepse:sync', [
        'mode' => 'daily',
        '--symbol' => ['AAA'],
    ])->assertExitCode(0);

    $syncLog = SyncLog::query()->latest('id')->first();

    expect($syncLog)->not()->toBeNull()
        ->and($syncLog?->type)->toBe(SyncMode::Daily)
        ->and($syncLog?->status)->toBe(SyncStatus::Completed)
        ->and($syncLog?->total_stocks)->toBe(1)
        ->and($syncLog?->processed_stocks)->toBe(1)
        ->and($syncLog?->total_synced)->toBe(1);

    expect(
        PriceHistory::query()
            ->whereDate('date', '2026-03-16')
            ->where('closing_price', 367.00)
            ->exists(),
    )->toBeTrue();

    $dailyRequests = collect(Http::recorded())
        ->pluck(0)
        ->filter(fn (HttpRequest $request): bool => str_starts_with($request->url(), config('nepse.nepalipaisa.daily_price_url')));

    expect($dailyRequests)->toHaveCount(7);
    Http::assertSent(fn (HttpRequest $request): bool => str_starts_with($request->url(), config('nepse.nepalipaisa.daily_price_url'))
        && str_contains($request->url(), 'stockSymbol=AAA')
        && str_contains($request->url(), 'tradeDate=2026-03-16'));
});

test('full sync command uses the configured fixed start date', function () {
    Carbon::setTestNow('2026-03-22 12:00:00');
    config()->set('nepse.sync.full_from_date', '2026-03-20');

    Http::fake(function (HttpRequest $request) {
        if ($request->url() === config('nepse.merolagani.market_url')) {
            return Http::response(commandMarketHtml(), 200);
        }

        if ($request->url() === 'https://merolagani.com/CompanyDetail.aspx?symbol=AAA') {
            return Http::response(commandDetailHtml('Alpha Bank', 'Commercial Bank'), 200);
        }

        if ($request->url() === config('nepse.nepalipaisa.history_url')) {
            return Http::response(historyPriceResponse('2026-03-20', '2026-03-22'), 200);
        }

        return Http::response([], 500);
    });

    $this->artisan('nepse:sync', [
        'mode' => 'full',
        '--symbol' => ['AAA'],
    ])
        ->expectsOutputToContain('[1/1] AAA | 2026-03-20 -> 2026-03-22')
        ->assertExitCode(0);

    $syncLog = SyncLog::query()->latest('id')->first();

    expect($syncLog?->type)->toBe(SyncMode::Full)
        ->and($syncLog?->status)->toBe(SyncStatus::Completed);

    $historyRequests = collect(Http::recorded())
        ->pluck(0)
        ->filter(fn (HttpRequest $request): bool => $request->url() === config('nepse.nepalipaisa.history_url'))
        ->values();

    expect($historyRequests)->toHaveCount(1);
    Http::assertSent(function (HttpRequest $request): bool {
        if ($request->url() !== config('nepse.nepalipaisa.history_url')) {
            return false;
        }

        $payload = json_decode($request->body(), true);

        return $payload['StockSymbol'] === 'AAA'
            && $payload['FromDate'] === '2026-03-20'
            && $payload['ToDate'] === '2026-03-22';
    });

    expect(PriceHistory::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(PriceHistory::query()->whereDate('date', '2026-03-20')->exists())->toBeTrue()
        ->and(PriceHistory::query()->whereDate('date', '2026-03-22')->exists())->toBeTrue();
});

test('full sync command rejects manual date overrides', function () {
    $this->artisan('nepse:sync', [
        'mode' => 'full',
        '--from' => '2026-03-20',
    ])->assertExitCode(1);

    expect(SyncLog::query()->count())->toBe(0);
});

test('smart sync command starts after the latest stored trade date', function () {
    Carbon::setTestNow('2026-03-22 12:00:00');

    $stock = Stock::factory()->create([
        'symbol' => 'AAA',
        'company_name' => 'Alpha Bank',
    ]);

    PriceHistory::factory()->for($stock)->create([
        'date' => '2026-03-20',
        'closing_price' => 360,
        'max_price' => 365,
        'min_price' => 355,
    ]);

    Http::fake(function (HttpRequest $request) {
        if ($request->url() === config('nepse.merolagani.market_url')) {
            return Http::response(commandMarketHtml(), 200);
        }

        if ($request->url() === 'https://merolagani.com/CompanyDetail.aspx?symbol=AAA') {
            return Http::response(commandDetailHtml('Alpha Bank', 'Commercial Bank'), 200);
        }

        if (str_starts_with($request->url(), config('nepse.nepalipaisa.daily_price_url'))) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return Http::response(dailyPriceResponse('AAA', (string) ($query['tradeDate'] ?? '2026-03-22')), 200);
        }

        return Http::response([], 500);
    });

    $this->artisan('nepse:sync', [
        'mode' => 'smart',
    ])->assertExitCode(0);

    $syncLog = SyncLog::query()->latest('id')->first();

    expect($syncLog?->type)->toBe(SyncMode::Smart)
        ->and($syncLog?->status)->toBe(SyncStatus::Completed);

    $dailyRequests = collect(Http::recorded())
        ->pluck(0)
        ->filter(fn (HttpRequest $request): bool => str_starts_with($request->url(), config('nepse.nepalipaisa.daily_price_url')))
        ->values();

    expect($dailyRequests)->toHaveCount(2)
        ->and($dailyRequests[0]->url())->toContain('tradeDate=2026-03-21')
        ->and($dailyRequests[1]->url())->toContain('tradeDate=2026-03-22');
});

test('smart sync command rejects manual overrides', function () {
    $this->artisan('nepse:sync', [
        'mode' => 'smart',
        '--from' => '2026-03-20',
    ])->assertExitCode(1);

    $this->artisan('nepse:sync', [
        'mode' => 'smart',
        '--to' => '2026-03-22',
    ])->assertExitCode(1);

    $this->artisan('nepse:sync', [
        'mode' => 'smart',
        '--symbol' => ['AAA'],
    ])->assertExitCode(1);

    expect(SyncLog::query()->count())->toBe(0);
});

test('sync command aborts early when the upstream daily endpoint returns 404', function () {
    Carbon::setTestNow('2026-03-22 12:00:00');

    Http::fake(function (HttpRequest $request) {
        if ($request->url() === config('nepse.merolagani.market_url')) {
            return Http::response(commandMarketHtml(), 200);
        }

        if ($request->url() === 'https://merolagani.com/CompanyDetail.aspx?symbol=AAA') {
            return Http::response(commandDetailHtml('Alpha Bank', 'Commercial Bank'), 200);
        }

        if (str_starts_with($request->url(), config('nepse.nepalipaisa.daily_price_url'))) {
            return Http::response([], 404);
        }

        return Http::response([], 500);
    });

    $this->artisan('nepse:sync', [
        'mode' => 'daily',
        '--symbol' => ['AAA'],
        '--days' => 1,
    ])->assertExitCode(1);

    $syncLog = SyncLog::query()->latest('id')->first();

    expect($syncLog)->not()->toBeNull()
        ->and($syncLog?->status)->toBe(SyncStatus::Failed)
        ->and($syncLog?->total_time)->toBeGreaterThanOrEqual(0)
        ->and($syncLog?->error_summary)->toContain('NepaliPaisa daily price endpoint returned 404');
});

test('stale sync log rows do not block the sync command', function () {
    Carbon::setTestNow('2026-03-22 12:00:00');

    SyncLog::factory()->create([
        'type' => SyncMode::Full,
        'status' => SyncStatus::Running,
    ]);

    Http::fake(function (HttpRequest $request) {
        if ($request->url() === config('nepse.merolagani.market_url')) {
            return Http::response(commandMarketHtml(), 200);
        }

        if ($request->url() === 'https://merolagani.com/CompanyDetail.aspx?symbol=AAA') {
            return Http::response(commandDetailHtml('Alpha Bank', 'Commercial Bank'), 200);
        }

        if (str_starts_with($request->url(), config('nepse.nepalipaisa.daily_price_url'))) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return Http::response(dailyPriceResponse('AAA', (string) ($query['tradeDate'] ?? '2026-03-22')), 200);
        }

        return Http::response([], 500);
    });

    $this->artisan('nepse:sync', [
        'mode' => 'daily',
        '--symbol' => ['AAA'],
        '--days' => 1,
    ])->assertExitCode(0);

    expect(SyncLog::query()->count())->toBe(2)
        ->and(SyncLog::query()->latest('id')->first()?->status)->toBe(SyncStatus::Completed);
});

test('sync command is blocked while another cli sync lock is active', function () {
    $lock = Cache::lock('nepse:sync:command', 7200);
    $lock->get();

    try {
        $this->artisan('nepse:sync')->assertExitCode(1);
    } finally {
        $lock->release();
    }
});

test('daily sync command is scheduled', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($scheduledEvent) => str_contains($scheduledEvent->command, 'nepse:sync daily'));

    expect($event)->not()->toBeNull()
        ->and($event->expression)->toBe('15 18 * * *');
});

afterEach(function () {
    Carbon::setTestNow();
});

function commandMarketHtml(): string
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

function commandDetailHtml(string $companyName, string $sector): string
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

function dailyPriceResponse(string $symbol, string $tradeDate): array
{
    return [
        'statusCode' => 200,
        'message' => 'Success',
        'result' => [
            'stocks' => [[
                'stockSymbol' => $symbol,
                'companyName' => 'Alpha Bank',
                'noOfTransactions' => 56,
                'maxPrice' => 367.00,
                'minPrice' => 359.00,
                'openingPrice' => 0.0,
                'closingPrice' => 367.00,
                'amount' => 2563497.00,
                'previousClosing' => 366.00,
                'differenceRs' => 1.00,
                'percentChange' => 0.27,
                'volume' => 7109,
                'ltv' => 0,
                'asOfDate' => $tradeDate.'T15:00:00',
                'asOfDateString' => 'As of Wed, 01 Jan 2020 | 03:00:00 PM',
                'tradeDate' => $tradeDate,
                'dataType' => null,
            ]],
            'summary' => [
                'totalAmount' => 2563497.00,
                'totalShares' => 7109,
                'totalTxns' => 56,
            ],
        ],
    ];
}

function historyPriceResponse(string ...$tradeDates): array
{
    return [
        'd' => collect($tradeDates)->map(fn (string $tradeDate): array => [
            'AsOfDateShortString' => $tradeDate,
            'ClosingPrice' => '367.00',
            'MaxPrice' => '370.00',
            'MinPrice' => '359.00',
            'Difference' => '1.00',
            'PercentDifference' => '0.27',
            'PreviousClosing' => '366.00',
            'TradedShares' => '7109',
            'TradedAmount' => '2563497',
            'TotalQuantity' => '7109',
            'TotalTransaction' => '56',
            'TotalAmount' => '2563497',
            'NoOfTransaction' => '56',
        ])->all(),
    ];
}
