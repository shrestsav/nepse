<?php

namespace App\Http\Controllers\Nepse;

use App\Http\Controllers\Controller;
use App\Models\PriceHistory;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $stocks = Stock::query()
            ->with(['sector', 'latestPriceHistory'])
            ->withCount('priceHistories')
            ->orderBy('symbol')
            ->paginate(25)
            ->through(fn (Stock $stock): array => [
                'id' => $stock->id,
                'symbol' => $stock->symbol,
                'companyName' => $stock->company_name,
                'sector' => $stock->sector?->name,
                'priceHistoryCount' => $stock->price_histories_count,
                'latestDate' => $stock->latestPriceHistory?->date?->toDateString(),
                'latestSyncedAt' => $stock->latestPriceHistory?->updated_at?->toIso8601String(),
                'latestClose' => $stock->latestPriceHistory?->closing_price,
            ])
            ->withQueryString();

        return Inertia::render('nepse/Stocks', [
            'stocks' => $stocks,
            'filters' => [
                'page' => (int) $request->integer('page', 1),
            ],
        ]);
    }

    public function show(Request $request, Stock $stock): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $bounds = $stock->priceHistories()
            ->selectRaw('MIN(date) as min_date, MAX(date) as max_date')
            ->first();

        $historyQuery = $stock->priceHistories()
            ->when(
                $filters['from'] ?? null,
                fn ($query, string $from) => $query->whereDate('date', '>=', $from),
            )
            ->when(
                $filters['to'] ?? null,
                fn ($query, string $to) => $query->whereDate('date', '<=', $to),
            );

        $matchingRecords = (clone $historyQuery)->count();

        /** @var object{min_date: string|null, max_date: string|null, low_price: float|null, high_price: float|null} $rangeAggregate */
        $rangeAggregate = (clone $historyQuery)
            ->selectRaw('MIN(date) as min_date, MAX(date) as max_date, MIN(min_price) as low_price, MAX(max_price) as high_price')
            ->first();

        $latestRecord = (clone $historyQuery)->first();
        $earliestRecord = (clone $historyQuery)
            ->reorder()
            ->orderBy('date')
            ->first();

        $priceHistories = $historyQuery
            ->limit(100)
            ->get()
            ->map(fn (PriceHistory $priceHistory): array => [
                'id' => $priceHistory->id,
                'date' => $priceHistory->date?->toDateString(),
                'close' => $priceHistory->closing_price,
                'high' => $priceHistory->max_price,
                'low' => $priceHistory->min_price,
                'change' => $priceHistory->change,
                'changePercent' => $priceHistory->change_percent,
                'previousClose' => $priceHistory->previous_closing,
                'volume' => $priceHistory->traded_shares,
                'transactions' => $priceHistory->no_of_transactions,
                'amount' => $priceHistory->total_amount,
            ])
            ->values();

        $rangeSummary = null;

        if ($matchingRecords > 0 && $rangeAggregate->min_date !== null && $rangeAggregate->max_date !== null) {
            $closeChange = null;
            $closeChangePercent = null;

            if ($earliestRecord instanceof PriceHistory && $latestRecord instanceof PriceHistory) {
                $closeChange = round($latestRecord->closing_price - $earliestRecord->closing_price, 2);

                if ($earliestRecord->closing_price !== 0.0) {
                    $closeChangePercent = round(($closeChange / $earliestRecord->closing_price) * 100, 2);
                }
            }

            $rangeSummary = [
                'matchingRecords' => $matchingRecords,
                'shownRecords' => $priceHistories->count(),
                'firstDate' => Carbon::parse($rangeAggregate->min_date)->toDateString(),
                'lastDate' => Carbon::parse($rangeAggregate->max_date)->toDateString(),
                'lowPrice' => $rangeAggregate->low_price !== null ? (float) $rangeAggregate->low_price : null,
                'highPrice' => $rangeAggregate->high_price !== null ? (float) $rangeAggregate->high_price : null,
                'earliestClose' => $earliestRecord?->closing_price !== null ? (float) $earliestRecord->closing_price : null,
                'latestClose' => $latestRecord?->closing_price !== null ? (float) $latestRecord->closing_price : null,
                'closeChange' => $closeChange,
                'closeChangePercent' => $closeChangePercent,
            ];
        }

        return Inertia::render('nepse/StockShow', [
            'stock' => [
                'id' => $stock->id,
                'symbol' => $stock->symbol,
                'companyName' => $stock->company_name,
                'sector' => $stock->sector?->name,
            ],
            'filters' => [
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'dateBounds' => [
                'min' => $bounds?->min_date ? Carbon::parse($bounds->min_date)->toDateString() : null,
                'max' => $bounds?->max_date ? Carbon::parse($bounds->max_date)->toDateString() : null,
            ],
            'rangeSummary' => $rangeSummary,
            'priceHistories' => $priceHistories,
        ]);
    }
}
