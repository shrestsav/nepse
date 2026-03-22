<?php

namespace App\Http\Controllers\Nepse;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use App\Services\Nepse\RecommendationService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(RecommendationService $recommendationService): Response
    {
        $summary = $recommendationService->summary();

        return Inertia::render('nepse/Dashboard', [
            'counts' => $summary['counts'],
            'recommendationCounts' => $summary['recommendationCounts'],
            'recommendationDate' => $summary['recommendationDate'],
            'latestSync' => $this->syncLogData($summary['latestSync']),
            'currentSync' => $this->syncLogData($summary['currentSync']),
        ]);
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
            'totalTime' => $syncLog->total_time,
            'totalSynced' => $syncLog->total_synced,
            'totalStocks' => $syncLog->total_stocks,
            'processedStocks' => $syncLog->processed_stocks,
            'errorSummary' => $syncLog->error_summary,
        ];
    }
}
