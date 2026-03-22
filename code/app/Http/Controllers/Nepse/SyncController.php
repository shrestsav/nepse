<?php

namespace App\Http\Controllers\Nepse;

use App\Enums\SyncMode;
use App\Enums\SyncStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartSyncRequest;
use App\Jobs\RunNepseSync;
use App\Models\SyncLog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SyncController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('nepse/Sync', [
            'currentSync' => $this->syncLogData(
                SyncLog::query()
                    ->whereIn('status', [SyncStatus::Queued->value, SyncStatus::Running->value])
                    ->latest('created_at')
                    ->first(),
            ),
            'latestSync' => $this->syncLogData(
                SyncLog::query()
                    ->whereNotNull('end')
                    ->latest('created_at')
                    ->first(),
            ),
            'modes' => collect(SyncMode::dashboardModes())->map(fn (SyncMode $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ])->all(),
        ]);
    }

    public function store(StartSyncRequest $request): RedirectResponse
    {
        $activeSync = SyncLog::query()
            ->whereIn('status', [SyncStatus::Queued->value, SyncStatus::Running->value])
            ->exists();

        if ($activeSync) {
            return to_route('dashboard.sync')->with('error', 'A sync is already in progress.');
        }

        $mode = $request->enum('mode', SyncMode::class);

        $syncLog = SyncLog::query()->create([
            'type' => $mode,
            'status' => SyncStatus::Queued,
            'start' => now(),
        ]);

        RunNepseSync::dispatch($syncLog->id);

        return to_route('dashboard.sync')->with('success', "{$mode->label()} sync queued.");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function syncLogData(?SyncLog $syncLog): ?array
    {
        if (! $syncLog instanceof SyncLog) {
            return null;
        }

        return [
            'id' => $syncLog->id,
            'type' => $syncLog->type?->value,
            'typeLabel' => $syncLog->type?->label(),
            'status' => $syncLog->status?->value,
            'start' => $syncLog->start?->toIso8601String(),
            'end' => $syncLog->end?->toIso8601String(),
            'batchId' => $syncLog->batch_id,
            'totalTime' => $syncLog->total_time,
            'totalSynced' => $syncLog->total_synced,
            'totalStocks' => $syncLog->total_stocks,
            'processedStocks' => $syncLog->processed_stocks,
            'errorSummary' => $syncLog->error_summary,
            'isRunning' => $syncLog->isRunning(),
        ];
    }
}
