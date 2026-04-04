<?php

use App\Models\Broker;
use App\Models\Floorsheet;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

test('floorsheet sync command defaults to the current date and syncs brokers by broker number', function () {
    Carbon::setTestNow('2026-03-26 12:00:00');

    Broker::query()->create([
        'broker_no' => '59',
        'broker_name' => 'Old Broker Name',
    ]);

    Http::fake(fakeFloorsheetHttp());

    $this->artisan('nepse:floorsheet-sync')
        ->expectsOutputToContain('Trade dates processed: 1')
        ->expectsOutputToContain('Date range: 2026-03-26 to 2026-03-26')
        ->assertExitCode(0);

    expect(Broker::query()->where('broker_no', '59')->value('broker_name'))->toBe('Broker Fifty Nine')
        ->and(Floorsheet::query()->count())->toBe(3)
        ->and(Floorsheet::query()->where('transaction', transactionIdForTradeDate('2026-03-26', '03012240'))->exists())->toBeTrue();

    Http::assertSent(function (HttpRequest $request): bool {
        if (! str_starts_with($request->url(), config('nepse.chukul.floorsheet_url'))) {
            return false;
        }

        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

        return ($query['date'] ?? null) === '2026-03-26'
            && ($query['page'] ?? null) === '1'
            && ($query['size'] ?? null) === '500';
    });
});

test('floorsheet sync command fetches multiple pages and is idempotent', function () {
    Carbon::setTestNow('2026-03-26 12:00:00');
    config()->set('nepse.chukul.floorsheet_page_size', 2);

    Http::fake(fakeFloorsheetHttp());

    $this->artisan('nepse:floorsheet-sync', [
        '--date' => '2026-03-26',
    ])->assertExitCode(0);

    $this->artisan('nepse:floorsheet-sync', [
        '--date' => '2026-03-26',
    ])->assertExitCode(0);

    expect(Floorsheet::query()->count())->toBe(3)
        ->and(Floorsheet::query()->where('transaction', transactionIdForTradeDate('2026-03-26', '03012234'))->value('quantity'))->toBe(400);

    $floorsheetRequests = collect(Http::recorded())
        ->pluck(0)
        ->filter(fn (HttpRequest $request): bool => str_starts_with($request->url(), config('nepse.chukul.floorsheet_url')))
        ->values();

    expect($floorsheetRequests)->toHaveCount(4)
        ->and($floorsheetRequests[0]->url())->toContain('page=1')
        ->and($floorsheetRequests[1]->url())->toContain('page=2');
});

test('floorsheet sync command accepts wrapped floorsheet responses and missing broker numbers', function () {
    Carbon::setTestNow('2026-03-29 12:00:00');

    Http::fake(function (HttpRequest $request) {
        if ($request->url() === config('nepse.merolagani.market_url')) {
            return Http::response(floorsheetCommandMarketHtml(), 200);
        }

        if ($request->url() === 'https://merolagani.com/CompanyDetail.aspx?symbol=AAA') {
            return Http::response(floorsheetCommandDetailHtml('Alpha Bank', 'Commercial Bank'), 200);
        }

        if ($request->url() === config('nepse.chukul.broker_url')) {
            return Http::response(floorsheetBrokerRows(), 200);
        }

        if (str_starts_with($request->url(), config('nepse.chukul.floorsheet_url'))) {
            return Http::response([
                'data' => [
                    [
                        'transaction' => transactionIdForTradeDate('2026-03-29', '01072318'),
                        'symbol' => 'AAA',
                        'quantity' => 10,
                        'rate' => 723,
                        'amount' => 7230,
                    ],
                ],
                'last_page' => 1,
            ], 200);
        }

        return Http::response([], 500);
    });

    $this->artisan('nepse:floorsheet-sync', [
        '--date' => '2026-03-29',
    ])->assertExitCode(0);

    $floorsheet = Floorsheet::query()
        ->where('transaction', transactionIdForTradeDate('2026-03-29', '01072318'))
        ->firstOrFail();

    expect($floorsheet->buyer_broker_no)->toBe('')
        ->and($floorsheet->seller_broker_no)->toBe('')
        ->and($floorsheet->buyer_broker_id)->toBeNull()
        ->and($floorsheet->seller_broker_id)->toBeNull()
        ->and($floorsheet->stock_id)->not()->toBeNull();
});

test('floorsheet sync command supports date range backfills', function () {
    Carbon::setTestNow('2026-03-28 12:00:00');

    Http::fake(fakeFloorsheetHttp());

    $this->artisan('nepse:floorsheet-sync', [
        '--from' => '2026-03-26',
        '--to' => '2026-03-27',
    ])
        ->expectsOutputToContain('Trade dates processed: 2')
        ->expectsOutputToContain('Date range: 2026-03-26 to 2026-03-27')
        ->assertExitCode(0);

    expect(Floorsheet::query()->count())->toBe(6)
        ->and(Floorsheet::query()->whereDate('trade_date', '2026-03-26')->count())->toBe(3)
        ->and(Floorsheet::query()->whereDate('trade_date', '2026-03-27')->count())->toBe(3);

    $floorsheetRequests = collect(Http::recorded())
        ->pluck(0)
        ->filter(fn (HttpRequest $request): bool => str_starts_with($request->url(), config('nepse.chukul.floorsheet_url')))
        ->values();

    expect($floorsheetRequests)->toHaveCount(2)
        ->and($floorsheetRequests[0]->url())->toContain('date=2026-03-26')
        ->and($floorsheetRequests[1]->url())->toContain('date=2026-03-27');
});

test('floorsheet sync command resolves stock and broker relations and keeps unresolved rows', function () {
    Carbon::setTestNow('2026-03-26 12:00:00');

    Http::fake(fakeFloorsheetHttp());

    $this->artisan('nepse:floorsheet-sync', [
        '--date' => '2026-03-26',
    ])->assertExitCode(0);

    $resolved = Floorsheet::query()->where('transaction', transactionIdForTradeDate('2026-03-26', '03012240'))->firstOrFail();
    $unresolved = Floorsheet::query()->where('transaction', transactionIdForTradeDate('2026-03-26', '03012234'))->firstOrFail();

    $stock = Stock::query()->where('symbol', 'AAA')->firstOrFail();
    $buyerBroker = Broker::query()->where('broker_no', '59')->firstOrFail();
    $sellerBroker = Broker::query()->where('broker_no', '49')->firstOrFail();

    expect($resolved->stock_id)->toBe($stock->id)
        ->and($resolved->buyer_broker_id)->toBe($buyerBroker->id)
        ->and($resolved->seller_broker_id)->toBe($sellerBroker->id)
        ->and($unresolved->stock_id)->toBeNull()
        ->and($unresolved->symbol)->toBe('ZZZ')
        ->and($unresolved->buyer_broker_no)->toBe('999')
        ->and($unresolved->buyer_broker_id)->toBeNull();
});

test('floorsheet sync command fails on malformed responses', function () {
    Carbon::setTestNow('2026-03-26 12:00:00');

    Http::fake(function (HttpRequest $request) {
        if ($request->url() === config('nepse.merolagani.market_url')) {
            return Http::response(floorsheetCommandMarketHtml(), 200);
        }

        if ($request->url() === 'https://merolagani.com/CompanyDetail.aspx?symbol=AAA') {
            return Http::response(floorsheetCommandDetailHtml('Alpha Bank', 'Commercial Bank'), 200);
        }

        if ($request->url() === config('nepse.chukul.broker_url')) {
            return Http::response(floorsheetBrokerRows(), 200);
        }

        if (str_starts_with($request->url(), config('nepse.chukul.floorsheet_url'))) {
            return Http::response([
                [
                    'transaction' => transactionIdForTradeDate('2026-03-26', '03012240'),
                    'symbol' => 'AAA',
                    'buyer' => '59',
                    'seller' => '49',
                    'quantity' => 'not-a-number',
                    'rate' => 385,
                    'amount' => 7700,
                ],
            ], 200);
        }

        return Http::response([], 500);
    });

    $this->artisan('nepse:floorsheet-sync', [
        '--date' => '2026-03-26',
    ])->assertExitCode(1);
});

test('floorsheet sync command retries transient chukul rate limits', function () {
    Carbon::setTestNow('2026-03-29 12:00:00');

    $floorsheetAttempts = 0;

    Http::fake(function (HttpRequest $request) use (&$floorsheetAttempts) {
        if ($request->url() === config('nepse.merolagani.market_url')) {
            return Http::response(floorsheetCommandMarketHtml(), 200);
        }

        if ($request->url() === 'https://merolagani.com/CompanyDetail.aspx?symbol=AAA') {
            return Http::response(floorsheetCommandDetailHtml('Alpha Bank', 'Commercial Bank'), 200);
        }

        if ($request->url() === config('nepse.chukul.broker_url')) {
            return Http::response(floorsheetBrokerRows(), 200);
        }

        if (str_starts_with($request->url(), config('nepse.chukul.floorsheet_url'))) {
            $floorsheetAttempts++;

            if ($floorsheetAttempts === 1) {
                return Http::response('Too Many Requests', 429);
            }

            return Http::response([
                'data' => [
                    [
                        'transaction' => transactionIdForTradeDate('2026-03-29', '01072318'),
                        'symbol' => 'AAA',
                        'quantity' => 10,
                        'rate' => 723,
                        'amount' => 7230,
                    ],
                ],
                'last_page' => 1,
            ], 200);
        }

        return Http::response([], 500);
    });

    $this->artisan('nepse:floorsheet-sync', [
        '--date' => '2026-03-29',
    ])->assertExitCode(0);

    $floorsheetRequests = collect(Http::recorded())
        ->pluck(0)
        ->filter(fn (HttpRequest $request): bool => str_starts_with($request->url(), config('nepse.chukul.floorsheet_url')))
        ->values();

    expect($floorsheetAttempts)->toBe(2)
        ->and($floorsheetRequests)->toHaveCount(2)
        ->and($floorsheetRequests[0]->header('Accept'))->toContain('application/json, text/plain, */*')
        ->and(implode('', $floorsheetRequests[0]->header('User-Agent')))->toContain('Chrome/146.0.0.0')
        ->and(implode('', $floorsheetRequests[0]->header('Sec-Fetch-Mode')))->toContain('cors');
});

test('floorsheet sync command fails on upstream http errors', function () {
    Http::fake(function (HttpRequest $request) {
        if ($request->url() === config('nepse.merolagani.market_url')) {
            return Http::response(floorsheetCommandMarketHtml(), 200);
        }

        if ($request->url() === 'https://merolagani.com/CompanyDetail.aspx?symbol=AAA') {
            return Http::response(floorsheetCommandDetailHtml('Alpha Bank', 'Commercial Bank'), 200);
        }

        if ($request->url() === config('nepse.chukul.broker_url')) {
            return Http::response([], 500);
        }

        return Http::response([], 500);
    });

    $this->artisan('nepse:floorsheet-sync')->assertExitCode(1);
});

test('floorsheet sync command validates conflicting or invalid date options', function () {
    $this->artisan('nepse:floorsheet-sync', [
        '--date' => '2026-03-26',
        '--from' => '2026-03-20',
    ])->assertExitCode(1);

    $this->artisan('nepse:floorsheet-sync', [
        '--to' => '2026-03-26',
    ])->assertExitCode(1);

    $this->artisan('nepse:floorsheet-sync', [
        '--from' => '2026-03-27',
        '--to' => '2026-03-26',
    ])->assertExitCode(1);
});

test('floorsheet sync command is scheduled', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($scheduledEvent) => str_contains($scheduledEvent->command, 'nepse:floorsheet-sync'));

    expect($event)->not()->toBeNull()
        ->and($event->expression)->toBe('45 18 * * *');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return Closure(HttpRequest): Response
 */
function fakeFloorsheetHttp(): Closure
{
    return function (HttpRequest $request) {
        if ($request->url() === config('nepse.merolagani.market_url')) {
            return Http::response(floorsheetCommandMarketHtml(), 200);
        }

        if ($request->url() === 'https://merolagani.com/CompanyDetail.aspx?symbol=AAA') {
            return Http::response(floorsheetCommandDetailHtml('Alpha Bank', 'Commercial Bank'), 200);
        }

        if ($request->url() === config('nepse.chukul.broker_url')) {
            return Http::response(floorsheetBrokerRows(), 200);
        }

        if (str_starts_with($request->url(), config('nepse.chukul.floorsheet_url'))) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
            $tradeDate = (string) ($query['date'] ?? '2026-03-26');
            $page = (int) ($query['page'] ?? 1);
            $size = (int) ($query['size'] ?? 500);

            return Http::response([
                'data' => floorsheetRowsForPage($tradeDate, $page, $size),
                'last_page' => floorsheetLastPage($size),
            ], 200);
        }

        return Http::response([], 500);
    };
}

/**
 * @return list<array{broker_no: string, broker_name: string}>
 */
function floorsheetBrokerRows(): array
{
    return [
        [
            'broker_no' => '49',
            'broker_name' => 'Broker Forty Nine',
        ],
        [
            'broker_no' => '57',
            'broker_name' => 'Broker Fifty Seven',
        ],
        [
            'broker_no' => '59',
            'broker_name' => 'Broker Fifty Nine',
        ],
    ];
}

/**
 * @return list<array<string, int|float|string>>
 */
function floorsheetRowsForPage(string $tradeDate, int $page, int $size): array
{
    $rows = [
        [
            'transaction' => transactionIdForTradeDate($tradeDate, '03012240'),
            'symbol' => 'AAA',
            'buyer' => '59',
            'seller' => '49',
            'quantity' => 20,
            'rate' => 385,
            'amount' => 7700,
        ],
        [
            'transaction' => transactionIdForTradeDate($tradeDate, '03012234'),
            'symbol' => 'ZZZ',
            'buyer' => '999',
            'seller' => '57',
            'quantity' => 400,
            'rate' => 384.8,
            'amount' => 153920,
        ],
        [
            'transaction' => transactionIdForTradeDate($tradeDate, '01062743'),
            'symbol' => 'AAA',
            'buyer' => '59',
            'seller' => '49',
            'quantity' => 60,
            'rate' => 974,
            'amount' => 58440,
        ],
    ];

    $offset = max(0, ($page - 1) * $size);

    return array_slice($rows, $offset, $size);
}

function floorsheetLastPage(int $size): int
{
    return 999999;
}

function transactionIdForTradeDate(string $tradeDate, string $suffix): string
{
    return str_replace('-', '', $tradeDate).$suffix;
}

function floorsheetCommandMarketHtml(): string
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

function floorsheetCommandDetailHtml(string $companyName, string $sector): string
{
    return <<<HTML
<html>
    <body>
        <table class="table table-striped">
            <tr>
                <th>Company Name</th>
                <td>{$companyName}</td>
            </tr>
            <tr>
                <th>Sector</th>
                <td>{$sector}</td>
            </tr>
        </table>
    </body>
</html>
HTML;
}
