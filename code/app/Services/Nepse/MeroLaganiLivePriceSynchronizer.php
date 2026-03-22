<?php

namespace App\Services\Nepse;

use App\Models\PriceHistory;
use App\Models\Stock;
use RuntimeException;

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

            $this->persistRow($stock, $row);

            $updated++;
        }

        return $updated;
    }

    /**
     * @return array{
     *     stockId: int,
     *     symbol: string,
     *     companyName: string,
     *     sector: string|null,
     *     marketDate: string,
     *     recordedAt: string,
     *     latestSyncedAt: string,
     *     price: float,
     *     change: float,
     *     changePercent: float,
     *     previousClose: float,
     *     high: float,
     *     low: float,
     *     open: float,
     *     volume: int
     * }
     */
    public function syncStock(Stock $stock): array
    {
        $row = collect($this->marketClient->fetchMarketRows())
            ->first(fn (array $marketRow): bool => ($marketRow['symbol'] ?? null) === $stock->symbol);

        if (! is_array($row)) {
            throw new RuntimeException("Stock symbol [{$stock->symbol}] was not present in the latest market snapshot.");
        }

        $priceHistory = $this->persistRow($stock, $row);

        return [
            'stockId' => $stock->id,
            'symbol' => $stock->symbol,
            'companyName' => $stock->company_name,
            'sector' => $stock->sector?->name,
            'marketDate' => $priceHistory->date?->toDateString() ?? now()->toDateString(),
            'recordedAt' => now()->toIso8601String(),
            'latestSyncedAt' => $priceHistory->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            'price' => (float) $priceHistory->closing_price,
            'change' => (float) $priceHistory->change,
            'changePercent' => (float) $priceHistory->change_percent,
            'previousClose' => (float) $priceHistory->previous_closing,
            'high' => (float) $priceHistory->max_price,
            'low' => (float) $priceHistory->min_price,
            'open' => $this->toFloat($row['open'], (float) $priceHistory->closing_price),
            'volume' => (int) $priceHistory->traded_shares,
        ];
    }

    private function persistRow(Stock $stock, array $row): PriceHistory
    {
        $today = now()->toDateString();
        $closingPrice = $this->toFloat($row['ltp']);
        $previousClosing = (float) ($stock->priceHistories()
            ->whereDate('date', '<', $today)
            ->orderByDesc('date')
            ->value('closing_price') ?? $closingPrice);

        return PriceHistory::query()->updateOrCreate(
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
