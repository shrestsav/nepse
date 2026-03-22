<?php

namespace App\Jobs;

use App\Enums\SyncMode;
use App\Models\Stock;
use App\Models\SyncLog;
use App\Services\Nepse\NepaliPaisaHistorySynchronizer;
use App\Services\Nepse\SyncLogTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncStockPriceHistory implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $syncLogId,
        public readonly int $stockId,
        public readonly SyncMode $mode,
    ) {
    }

    public function handle(
        NepaliPaisaHistorySynchronizer $historySynchronizer,
        SyncLogTracker $tracker,
    ): void {
        $syncLog = SyncLog::query()->findOrFail($this->syncLogId);
        $stock = Stock::query()->findOrFail($this->stockId);

        try {
            $historySynchronizer->sync($stock, $this->mode);
            $tracker->recordProcessedStock($syncLog, true);
        } catch (Throwable $throwable) {
            $tracker->recordProcessedStock(
                $syncLog,
                false,
                "{$stock->symbol}: {$throwable->getMessage()}",
            );
        }
    }
}
