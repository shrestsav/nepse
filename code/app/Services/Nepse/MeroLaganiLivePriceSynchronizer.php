<?php

namespace App\Services\Nepse;

use App\Models\PriceHistory;
use App\Models\Stock;

class MeroLaganiLivePriceSynchronizer
{
    public function __construct(
        private readonly MeroLaganiMarketClient $marketClient,
    ) {
    }

    public function sync(): int
    {
        $rows = collect($this->marketClient->fetchMarketRows())
            ->filter(fn (array $row): bool => filled($row['symbol']))
            ->values();

        if ($rows->isEmpty()) {
            return 0;
        }

        $stocks = Stock::query()
            ->whereIn('symbol', $rows->pluck('symbol'))
            ->get()
            ->keyBy('symbol');

        $today = now()->toDateString();
        $updated = 0;

        foreach ($rows as $row) {
            $stock = $stocks->get($row['symbol']);

            if (! $stock instanceof Stock) {
                continue;
            }

            $closingPrice = $this->toFloat($row['ltp']);
            $previousClosing = (float) ($stock->priceHistories()
                ->whereDate('date', '<', $today)
                ->orderByDesc('date')
                ->value('closing_price') ?? $closingPrice);

            PriceHistory::query()->updateOrCreate(
                [
                    'stock_id' => $stock->id,
                    'date' => $today,
                ],
                [
                    'closing_price' => $closingPrice,
                    'max_price' => $this->toFloat($row['high'], $closingPrice),
                    'min_price' => $this->toFloat($row['low'], $closingPrice),
                    'change' => round($closingPrice - $previousClosing, 2),
                    'change_percent' => $this->toFloat($row['change_percent']),
                    'previous_closing' => $previousClosing,
                    'traded_shares' => $this->toInt($row['quantity']),
                    'traded_amount' => 0,
                    'total_quantity' => 0,
                    'total_transaction' => 0,
                    'total_amount' => 0,
                    'no_of_transactions' => 0,
                ],
            );

            $updated++;
        }

        return $updated;
    }

    private function toFloat(?string $value, float $fallback = 0): float
    {
        $normalized = trim(str_replace([',', '%'], '', (string) $value));

        return is_numeric($normalized) ? round((float) $normalized, 2) : $fallback;
    }

    private function toInt(?string $value): int
    {
        $normalized = trim(str_replace(',', '', (string) $value));

        return is_numeric($normalized) ? (int) $normalized : 0;
    }
}
