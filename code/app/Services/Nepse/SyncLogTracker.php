<?php

namespace App\Services\Nepse;

use App\Enums\SyncStatus;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncLogTracker
{
    public function markRunning(SyncLog $syncLog): void
    {
        $syncLog->forceFill([
            'status' => SyncStatus::Running,
        ])->save();
    }

    public function attachBatch(SyncLog $syncLog, string $batchId, int $totalStocks): void
    {
        $syncLog->forceFill([
            'batch_id' => $batchId,
            'total_stocks' => $totalStocks,
        ])->save();
    }

    public function recordProcessedStock(SyncLog $syncLog, bool $successful, ?string $error = null): void
    {
        DB::transaction(function () use ($syncLog, $successful, $error): void {
            $fresh = SyncLog::query()->lockForUpdate()->findOrFail($syncLog->id);
            $fresh->processed_stocks++;

            if ($successful) {
                $fresh->total_synced++;
            }

            if (filled($error)) {
                $fresh->error_summary = $this->appendError($fresh->error_summary, $error);
            }

            $fresh->save();
        });
    }

    public function complete(SyncLog $syncLog): void
    {
        $fresh = SyncLog::query()->findOrFail($syncLog->id);

        if ($fresh->status === SyncStatus::Failed) {
            return;
        }

        $fresh->forceFill([
            'status' => filled($fresh->error_summary)
                ? SyncStatus::CompletedWithErrors
                : SyncStatus::Completed,
            'end' => now(),
            'total_time' => $this->elapsedSeconds($fresh->start),
        ])->save();
    }

    public function completeImmediately(SyncLog $syncLog, int $processedStocks, int $successfulStocks): void
    {
        $syncLog->forceFill([
            'status' => SyncStatus::Completed,
            'end' => now(),
            'total_time' => $this->elapsedSeconds($syncLog->start),
            'total_stocks' => $processedStocks,
            'processed_stocks' => $processedStocks,
            'total_synced' => $successfulStocks,
        ])->save();
    }

    public function fail(SyncLog $syncLog, string $error): void
    {
        DB::transaction(function () use ($syncLog, $error): void {
            $fresh = SyncLog::query()->lockForUpdate()->findOrFail($syncLog->id);
            $fresh->forceFill([
                'status' => SyncStatus::Failed,
                'end' => now(),
                'total_time' => $this->elapsedSeconds($fresh->start),
                'error_summary' => $this->appendError($fresh->error_summary, $error),
            ])->save();
        });
    }

    private function appendError(?string $currentSummary, string $error): string
    {
        return Str::limit(
            trim(collect([$currentSummary, $error])
            ->filter(fn (?string $line): bool => filled($line))
            ->implode("\n")),
            60_000,
            "\n...[truncated]",
        );
    }

    private function elapsedSeconds(\DateTimeInterface|string|null $start): int
    {
        if (! $start instanceof \DateTimeInterface) {
            return 0;
        }

        return max(0, now()->getTimestamp() - $start->getTimestamp());
    }
}
