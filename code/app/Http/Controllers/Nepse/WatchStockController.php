<?php

namespace App\Http\Controllers\Nepse;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Services\Nepse\MeroLaganiLivePriceSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WatchStockController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('nepse/WatchStock', [
            'stocks' => Stock::query()
                ->with('sector')
                ->orderBy('symbol')
                ->get()
                ->map(fn (Stock $stock): array => [
                    'id' => $stock->id,
                    'symbol' => $stock->symbol,
                    'companyName' => $stock->company_name,
                    'sector' => $stock->sector?->name,
                ])
                ->all(),
        ]);
    }

    public function quote(Request $request, MeroLaganiLivePriceSynchronizer $livePriceSynchronizer): JsonResponse
    {
        $validated = $request->validate([
            'stock' => ['required', 'integer', 'exists:stocks,id'],
        ]);

        $stock = Stock::query()->with('sector')->findOrFail($validated['stock']);

        try {
            return response()->json([
                'quote' => $livePriceSynchronizer->syncStock($stock),
            ]);
        } catch (Throwable $throwable) {
            return response()->json([
                'message' => $throwable->getMessage(),
            ], 422);
        }
    }
}
