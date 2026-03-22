<?php

use App\Enums\SyncMode;
use App\Enums\SyncStatus;
use App\Jobs\RunNepseSync;
use App\Jobs\SyncStockPriceHistory;
use App\Models\PriceHistory;
use App\Models\SyncLog;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('full sync imports the catalog and dispatches a history batch', function () {
    Bus::fake();

    Http::fake([
        config('nepse.merolagani.market_url') => Http::response(runSyncMarketHtml(), 200),
        'https://merolagani.com/CompanyDetail.aspx?symbol=AAA' => Http::response(runSyncDetailHtml('Alpha Bank', 'Commercial Bank'), 200),
    ]);

    $syncLog = SyncLog::query()->create([
        'type' => SyncMode::Full,
        'status' => SyncStatus::Queued,
        'start' => now(),
    ]);

    app()->call([new RunNepseSync($syncLog->id), 'handle']);

    Bus::assertBatched(function (PendingBatch $batch): bool {
        $job = $batch->jobs->first();

        return $batch->jobs->count() === 1
            && $job instanceof SyncStockPriceHistory
            && $job->mode === SyncMode::Full;
    });

    expect($syncLog->fresh()->status)->toBe(SyncStatus::Running)
        ->and($syncLog->fresh()->batch_id)->not->toBeNull()
        ->and($syncLog->fresh()->total_stocks)->toBe(1);
});

test('smart sync dispatches stock history jobs in smart mode', function () {
    Bus::fake();

    Http::fake([
        config('nepse.merolagani.market_url') => Http::response(runSyncMarketHtml(), 200),
        'https://merolagani.com/CompanyDetail.aspx?symbol=AAA' => Http::response(runSyncDetailHtml('Alpha Bank', 'Commercial Bank'), 200),
    ]);

    $syncLog = SyncLog::query()->create([
        'type' => SyncMode::Smart,
        'status' => SyncStatus::Queued,
        'start' => now(),
    ]);

    app()->call([new RunNepseSync($syncLog->id), 'handle']);

    Bus::assertBatched(function (PendingBatch $batch): bool {
        $job = $batch->jobs->first();

        return $job instanceof SyncStockPriceHistory
            && $job->mode === SyncMode::Smart;
    });
});

test('live sync completes immediately and stores todays price', function () {
    Http::fake([
        config('nepse.merolagani.market_url') => Http::response(runSyncMarketHtml(), 200),
        'https://merolagani.com/CompanyDetail.aspx?symbol=AAA' => Http::response(runSyncDetailHtml('Alpha Bank', 'Commercial Bank'), 200),
    ]);

    $syncLog = SyncLog::query()->create([
        'type' => SyncMode::Live,
        'status' => SyncStatus::Queued,
        'start' => now(),
    ]);

    app()->call([new RunNepseSync($syncLog->id), 'handle']);

    expect($syncLog->fresh()->status)->toBe(SyncStatus::Completed)
        ->and($syncLog->fresh()->total_synced)->toBe(1)
        ->and(PriceHistory::query()->whereDate('date', now()->toDateString())->count())->toBe(1);
});

function runSyncMarketHtml(): string
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

function runSyncDetailHtml(string $companyName, string $sector): string
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
