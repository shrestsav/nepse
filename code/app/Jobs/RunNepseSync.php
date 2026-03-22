<?php

namespace App\Jobs;

use App\Enums\SyncMode;
use App\Models\Stock;
use App\Models\SyncLog;
use App\Services\Nepse\MeroLaganiCatalogImporter;
use App\Services\Nepse\MeroLaganiLivePriceSynchronizer;
use App\Services\Nepse\SyncLogTracker;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Throwable;

class RunNepseSync implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $syncLogId,
    ) {
    }

    public function handle(
        MeroLaganiCatalogImporter $catalogImporter,
        MeroLaganiLivePriceSynchronizer $livePriceSynchronizer,
        SyncLogTracker $tracker,
    ): void {
        $syncLog = SyncLog::query()->findOrFail($this->syncLogId);

        try {
            $tracker->markRunning($syncLog);
            $catalogImporter->sync();
            $syncLog->refresh();

            if ($syncLog->type === SyncMode::Live) {
                $updatedStocks = $livePriceSynchronizer->sync();
                $tracker->completeImmediately($syncLog, $updatedStocks, $updatedStocks);

                return;
            }

            $stocks = Stock::query()
                ->orderBy('symbol')
                ->get(['id']);

            if ($stocks->isEmpty()) {
                $tracker->completeImmediately($syncLog, 0, 0);

                return;
            }

            $jobs = $stocks->map(
                fn (Stock $stock): SyncStockPriceHistory => new SyncStockPriceHistory(
                    $syncLog->id,
                    $stock->id,
                    $syncLog->type,
                ),
            )->all();

            $syncLogId = $syncLog->id;

            $batch = Bus::batch($jobs)
                ->name("nepse-{$syncLog->type->value}-{$syncLog->id}")
                ->finally(function (Batch $batch) use ($syncLogId): void {
                    $fresh = SyncLog::query()->findOrFail($syncLogId);
                    app(SyncLogTracker::class)->complete($fresh);
                })
                ->dispatch();

            $tracker->attachBatch($syncLog, $batch->id, count($jobs));
        } catch (Throwable $throwable) {
            $tracker->fail($syncLog, $throwable->getMessage());
        }
    }
}
