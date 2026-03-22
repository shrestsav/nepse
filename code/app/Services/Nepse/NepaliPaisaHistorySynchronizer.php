<?php

namespace App\Services\Nepse;

use App\Enums\SyncMode;
use App\Models\PriceHistory;
use App\Models\Stock;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NepaliPaisaHistorySynchronizer
{
    public function sync(Stock $stock, SyncMode $mode): int
    {
        $fromDate = $this->resolveFromDate($stock, $mode);
        $toDate = now()->toDateString();

        if ($fromDate > $toDate) {
            return 0;
        }

        $payload = [
            'StockSymbol' => $stock->symbol,
            'FromDate' => $fromDate,
            'ToDate' => $toDate,
            'Offset' => 1,
            'Limit' => (int) config('nepse.nepalipaisa.limit'),
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'Accept' => 'application/json',
                ])
                ->post(config('nepse.nepalipaisa.history_url'), $payload)
                ->throw();
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 404) {
                throw new RuntimeException(
                    "NepaliPaisa history endpoint returned 404 for [{$stock->symbol}]. ".
                    'The configured history URL is currently unavailable upstream.',
                    previous: $exception,
                );
            }

            throw $exception;
        }

        $histories = $response->json('d');

        if (! is_array($histories)) {
            throw new RuntimeException("Unexpected history response for [{$stock->symbol}].");
        }

        foreach ($histories as $history) {
            if (! is_array($history) || blank($history['AsOfDateShortString'] ?? null)) {
                continue;
            }

            $date = Carbon::parse($history['AsOfDateShortString'])->toDateString();

            PriceHistory::query()->updateOrCreate(
                [
                    'stock_id' => $stock->id,
                    'date' => $date,
                ],
                [
                    'closing_price' => $this->toFloat($history['ClosingPrice'] ?? null),
                    'max_price' => $this->toFloat($history['MaxPrice'] ?? null),
                    'min_price' => $this->toFloat($history['MinPrice'] ?? null),
                    'change' => $this->toFloat($history['Difference'] ?? null),
                    'change_percent' => $this->toFloat($history['PercentDifference'] ?? null),
                    'previous_closing' => $this->toFloat($history['PreviousClosing'] ?? null),
                    'traded_shares' => $this->toInt($history['TradedShares'] ?? null),
                    'traded_amount' => $this->toInt($history['TradedAmount'] ?? null),
                    'total_quantity' => $this->toInt($history['TotalQuantity'] ?? null),
                    'total_transaction' => $this->toInt($history['TotalTransaction'] ?? null),
                    'total_amount' => $this->toFloat($history['TotalAmount'] ?? null),
                    'no_of_transactions' => $this->toInt($history['NoOfTransaction'] ?? null),
                ],
            );
        }

        return count($histories);
    }

    private function resolveFromDate(Stock $stock, SyncMode $mode): string
    {
        if ($mode === SyncMode::Full) {
            return (string) config('nepse.sync.full_from_date');
        }

        $latestDate = $stock->priceHistories()
            ->orderByDesc('date')
            ->value('date');

        if ($latestDate === null) {
            return (string) config('nepse.sync.full_from_date');
        }

        return Carbon::parse($latestDate)->addDay()->toDateString();
    }

    private function toFloat(mixed $value): float
    {
        $normalized = trim(str_replace([',', '%'], '', (string) $value));

        return is_numeric($normalized) ? round((float) $normalized, 2) : 0;
    }

    private function toInt(mixed $value): int
    {
        $normalized = trim(str_replace(',', '', (string) $value));

        return is_numeric($normalized) ? (int) $normalized : 0;
    }
}
