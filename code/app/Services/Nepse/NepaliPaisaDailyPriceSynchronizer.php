<?php

namespace App\Services\Nepse;

use App\Models\PriceHistory;
use App\Models\Stock;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NepaliPaisaDailyPriceSynchronizer
{
    /**
     * @param Collection<int, string> $selectedSymbols
     * @return array{rowsSynced: int, syncedSymbols: list<string>}
     */
    public function syncTradeDate(string $tradeDate, Collection $selectedSymbols): array
    {
        $requestedSymbol = $selectedSymbols->count() === 1
            ? (string) $selectedSymbols->first()
            : '';

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->get(config('nepse.nepalipaisa.daily_price_url'), [
                    'stockSymbol' => $requestedSymbol,
                    'tradeDate' => $tradeDate,
                ])
                ->throw();
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 404) {
                throw new RuntimeException(
                    "NepaliPaisa daily price endpoint returned 404 for [{$tradeDate}]. ".
                    'The configured daily price URL is currently unavailable upstream.',
                    previous: $exception,
                );
            }

            throw $exception;
        }

        $rows = $response->json('result.stocks');

        if (! is_array($rows)) {
            throw new RuntimeException("Unexpected daily price response for [{$tradeDate}].");
        }

        $selectedLookup = $selectedSymbols
            ->mapWithKeys(fn (string $symbol): array => [$symbol => true]);

        $normalizedRows = collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['stockSymbol'] ?? null))
            ->map(function (array $row): array {
                $row['stockSymbol'] = strtoupper(trim((string) $row['stockSymbol']));

                return $row;
            })
            ->filter(fn (array $row): bool => $selectedLookup->isEmpty()
                || $selectedLookup->has($row['stockSymbol']))
            ->values();

        if ($normalizedRows->isEmpty()) {
            return [
                'rowsSynced' => 0,
                'syncedSymbols' => [],
            ];
        }

        $stocksBySymbol = Stock::query()
            ->whereIn('symbol', $normalizedRows->pluck('stockSymbol')->all())
            ->get(['id', 'symbol'])
            ->keyBy('symbol');

        $rowsSynced = 0;
        $syncedSymbols = [];

        foreach ($normalizedRows as $row) {
            $stock = $stocksBySymbol->get($row['stockSymbol']);

            if (! $stock instanceof Stock) {
                continue;
            }

            $date = filled($row['tradeDate'] ?? null)
                ? Carbon::parse($row['tradeDate'])->toDateString()
                : Carbon::parse($row['asOfDate'] ?? $tradeDate)->toDateString();

            PriceHistory::query()->updateOrCreate(
                [
                    'stock_id' => $stock->id,
                    'date' => $date,
                ],
                [
                    'closing_price' => $this->toFloat($row['closingPrice'] ?? null),
                    'max_price' => $this->toFloat($row['maxPrice'] ?? null),
                    'min_price' => $this->toFloat($row['minPrice'] ?? null),
                    'change' => $this->toFloat($row['differenceRs'] ?? null),
                    'change_percent' => $this->toFloat($row['percentChange'] ?? null),
                    'previous_closing' => $this->toFloat($row['previousClosing'] ?? null),
                    'traded_shares' => $this->toInt($row['volume'] ?? null),
                    'traded_amount' => $this->toInt($row['amount'] ?? null),
                    'total_quantity' => $this->toInt($row['volume'] ?? null),
                    'total_transaction' => $this->toInt($row['noOfTransactions'] ?? null),
                    'total_amount' => $this->toFloat($row['amount'] ?? null),
                    'no_of_transactions' => $this->toInt($row['noOfTransactions'] ?? null),
                ],
            );

            $rowsSynced++;
            $syncedSymbols[$stock->symbol] = true;
        }

        return [
            'rowsSynced' => $rowsSynced,
            'syncedSymbols' => array_keys($syncedSymbols),
        ];
    }

    private function toFloat(mixed $value): float
    {
        $normalized = trim(str_replace([',', '%'], '', (string) $value));

        return is_numeric($normalized) ? round((float) $normalized, 2) : 0;
    }

    private function toInt(mixed $value): int
    {
        $normalized = trim(str_replace(',', '', (string) $value));

        return is_numeric($normalized) ? (int) round((float) $normalized) : 0;
    }
}
