<?php

namespace App\Services\Nepse;

use App\Models\PriceHistory;
use App\Models\Stock;
use Illuminate\Http\Client\ConnectionException;
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
            $response = Http::timeout(45)
                ->retry(3, fn (int $attempt): int => $attempt * 1500, throw: false)
                ->withHeaders($this->requestHeaders())
                ->get(config('nepse.nepalipaisa.daily_price_url'), [
                    'stockSymbol' => $requestedSymbol,
                    'tradeDate' => $tradeDate,
                ])
                ->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                "NepaliPaisa daily price request failed for [{$tradeDate}] after retries.",
                previous: $exception,
            );
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 404) {
                throw new RuntimeException(
                    "NepaliPaisa daily price endpoint returned 404 for [{$tradeDate}]. ".
                    'The configured daily price URL is currently unavailable upstream.',
                    previous: $exception,
                );
            }

            if ($exception->response?->status() === 522) {
                throw new RuntimeException(
                    "NepaliPaisa daily price endpoint timed out upstream for [{$tradeDate}] after retries.",
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

    /**
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Content-Type' => 'application/json; charset=utf-8',
            'Priority' => 'u=1, i',
            'Referer' => 'https://nepalipaisa.com/today-share-price',
            'Sec-CH-UA' => '"Chromium";v="146", "Not-A.Brand";v="24", "Google Chrome";v="146"',
            'Sec-CH-UA-Mobile' => '?0',
            'Sec-CH-UA-Platform' => '"macOS"',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
