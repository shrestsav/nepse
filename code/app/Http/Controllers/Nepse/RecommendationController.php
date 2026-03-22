<?php

namespace App\Http\Controllers\Nepse;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use App\Services\Nepse\RecommendationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecommendationController extends Controller
{
    public function index(Request $request, RecommendationService $recommendationService): Response
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);
        $selectedDate = $recommendationService->resolveAsOfDate($validated['date'] ?? null);

        return Inertia::render('nepse/Recommendations', [
            'groups' => $recommendationService->buildRecommendationGroups($selectedDate),
            'selectedDate' => $selectedDate?->toDateString(),
            'requestedDate' => $validated['date'] ?? null,
            'dateBounds' => [
                'min' => $recommendationService->earliestTradingDate()?->toDateString(),
                'max' => $recommendationService->latestTradingDate()?->toDateString(),
            ],
            'latestSync' => $this->syncLogData(
                SyncLog::query()->whereNotNull('end')->latest('created_at')->first(),
            ),
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
            'end' => $syncLog->end?->toIso8601String(),
        ];
    }
}
