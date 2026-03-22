<?php

namespace App\Services\Nepse;

use App\Models\PriceHistory;
use App\Models\Sector;
use App\Models\Stock;
use App\Models\SyncLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class RecommendationService
{
    public function __construct(
        private readonly TechnicalIndicators $indicators,
    ) {
    }

    public function earliestTradingDate(): ?CarbonImmutable
    {
        $date = PriceHistory::query()->min('date');

        return filled($date) ? CarbonImmutable::parse($date) : null;
    }

    public function latestTradingDate(): ?CarbonImmutable
    {
        $date = PriceHistory::query()->max('date');

        return filled($date) ? CarbonImmutable::parse($date) : null;
    }

    public function resolveAsOfDate(?string $requestedDate = null): ?CarbonImmutable
    {
        $query = PriceHistory::query();

        if (filled($requestedDate)) {
            $query->whereDate('date', '<=', $requestedDate);
        }

        $date = $query->max('date');

        return filled($date) ? CarbonImmutable::parse($date) : null;
    }

    /**
     * @return array{
     *     rsiAdx: array{buy: list<array<string, mixed>>, sell: list<array<string, mixed>>},
     *     rsiMacd: array{buy: list<array<string, mixed>>, sell: list<array<string, mixed>>},
     *     maEmaAdx: array{buy: list<array<string, mixed>>, sell: list<array<string, mixed>>}
     * }
     */
    public function buildRecommendationGroups(?CarbonImmutable $asOfDate = null): array
    {
        $effectiveDate = $asOfDate ?? $this->resolveAsOfDate();

        if (! $effectiveDate instanceof CarbonImmutable) {
            return $this->emptyGroups();
        }

        $historyStartDate = $this->recommendationHistoryStartDate($effectiveDate, (int) config('nepse.recommendations.page_lookback_days', 120));

        $stocks = Stock::query()
            ->with([
                'sector',
                'latestPriceHistory',
                'priceHistories' => fn ($query) => $query
                    ->whereDate('date', '>=', $historyStartDate)
                    ->whereDate('date', '<=', $effectiveDate->toDateString()),
            ])
            ->get();

        return [
            'rsiAdx' => $this->rsiAdxRecommendations($stocks),
            'rsiMacd' => $this->rsiMacdRecommendations($stocks),
            'maEmaAdx' => $this->maEmaAdxRecommendations($stocks),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $asOfDate = $this->resolveAsOfDate();
        $recommendationCounts = $this->summaryRecommendationCounts($asOfDate);

        return [
            'counts' => [
                'stocks' => Stock::query()->count(),
                'sectors' => Sector::query()->count(),
                'priceHistories' => PriceHistory::query()->count(),
            ],
            'recommendationCounts' => $recommendationCounts,
            'recommendationDate' => $asOfDate?->toDateString(),
            'latestSync' => SyncLog::query()
                ->whereNotNull('end')
                ->latest('created_at')
                ->first(),
            'currentSync' => SyncLog::query()
                ->whereIn('status', ['queued', 'running'])
                ->latest('created_at')
                ->first(),
        ];
    }

    /**
     * Build dashboard counts in chunks so the dashboard does not materialize the full
     * recommendation payload graph for every stock on a single request.
     *
     * @return array{rsiAdx: int, rsiMacd: int, maEmaAdx: int}
     */
    private function summaryRecommendationCounts(?CarbonImmutable $asOfDate): array
    {
        if (! $asOfDate instanceof CarbonImmutable) {
            return [
                'rsiAdx' => 0,
                'rsiMacd' => 0,
                'maEmaAdx' => 0,
            ];
        }

        $historyStartDate = $this->recommendationHistoryStartDate(
            $asOfDate,
            (int) config('nepse.recommendations.dashboard_summary_lookback_days', 30),
        );

        $counts = [
            'rsiAdx' => 0,
            'rsiMacd' => 0,
            'maEmaAdx' => 0,
        ];

        Stock::query()
            ->orderBy('id')
            ->chunkById(25, function (Collection $stocks) use (&$counts, $asOfDate, $historyStartDate): void {
                $stocks->load([
                    'sector',
                    'latestPriceHistory',
                    'priceHistories' => fn ($query) => $query
                        ->whereDate('date', '>=', $historyStartDate)
                        ->whereDate('date', '<=', $asOfDate->toDateString()),
                ]);

                $counts['rsiAdx'] += $this->countSignals($this->rsiAdxRecommendations($stocks));
                $counts['rsiMacd'] += $this->countSignals($this->rsiMacdRecommendations($stocks));
                $counts['maEmaAdx'] += $this->countSignals($this->maEmaAdxRecommendations($stocks));
            });

        return $counts;
    }

    private function recommendationHistoryStartDate(CarbonImmutable $asOfDate, int $lookbackDays): string
    {
        $configuredStart = CarbonImmutable::parse((string) config('nepse.recommendations.history_start_date'));

        return $asOfDate
            ->subDays(max(1, $lookbackDays) - 1)
            ->max($configuredStart)
            ->toDateString();
    }

    /**
     * @param Collection<int, Stock> $stocks
     * @return array{buy: list<array<string, mixed>>, sell: list<array<string, mixed>>}
     */
    private function rsiAdxRecommendations(Collection $stocks): array
    {
        $profile = config('nepse.recommendations.profiles.rsi_adx');
        $buy = [];
        $sell = [];

        foreach ($stocks as $stock) {
            $histories = $stock->priceHistories->sortBy('date')->values();

            if (! $this->shouldEvaluate($stock, $histories->count(), 'rsi_adx')) {
                continue;
            }

            [$high, $low, $close] = $this->extractOhlc($histories);
            $adx = $this->indicators->adx($high, $low, $close, (int) $profile['adx_period']);
            $rsi = $this->indicators->rsi($close, (int) $profile['rsi_period']);

            if (! $this->hasTwoRecentValues($adx) || ! $this->hasTwoRecentValues($rsi)) {
                continue;
            }

            $latestAdx = $adx[array_key_last($adx)];
            $previousAdx = $adx[array_key_last($adx) - 1];
            $latestRsi = $rsi[array_key_last($rsi)];
            $previousRsi = $rsi[array_key_last($rsi) - 1];

            $deltas = [
                'adx' => $latestAdx - $previousAdx,
                'rsi' => $latestRsi - $previousRsi,
            ];

            if ($latestAdx > $previousAdx
                && $latestAdx > (float) $profile['adx_min']
                && $latestRsi > $previousRsi
                && ($latestRsi - $previousRsi) > (float) $profile['rsi_diff_min']
                && $latestRsi > (float) $profile['rsi_min']) {
                $buy[] = $this->buildRecommendationPayload($stock, $histories, [
                    'adx' => $adx,
                    'rsi' => $rsi,
                ], $deltas);

                continue;
            }

            if ($latestAdx < $previousAdx && $latestRsi < $previousRsi) {
                $sell[] = $this->buildRecommendationPayload($stock, $histories, [
                    'adx' => $adx,
                    'rsi' => $rsi,
                ], $deltas);
            }
        }

        return [
            'buy' => $this->sortRecommendations($buy),
            'sell' => $this->sortRecommendations($sell),
        ];
    }

    /**
     * @param Collection<int, Stock> $stocks
     * @return array{buy: list<array<string, mixed>>, sell: list<array<string, mixed>>}
     */
    private function rsiMacdRecommendations(Collection $stocks): array
    {
        $profile = config('nepse.recommendations.profiles.rsi_macd');
        $buy = [];

        foreach ($stocks as $stock) {
            $histories = $stock->priceHistories->sortBy('date')->values();

            if (! $this->shouldEvaluate($stock, $histories->count(), 'rsi_macd')) {
                continue;
            }

            [$high, $low, $close] = $this->extractOhlc($histories);
            $macd = $this->indicators->macd(
                $close,
                (int) $profile['macd_fast_period'],
                (int) $profile['macd_slow_period'],
            );
            $rsi = $this->indicators->rsi($close, (int) $profile['rsi_period']);

            if (! $this->hasTwoRecentValues($macd) || ! $this->hasTwoRecentValues($rsi)) {
                continue;
            }

            $latestMacd = $macd[array_key_last($macd)];
            $previousMacd = $macd[array_key_last($macd) - 1];
            $latestRsi = $rsi[array_key_last($rsi)];
            $previousRsi = $rsi[array_key_last($rsi) - 1];

            if ($latestMacd > $previousMacd
                && $latestRsi > $previousRsi
                && $latestRsi > (float) $profile['rsi_min']) {
                $buy[] = $this->buildRecommendationPayload($stock, $histories, [
                    'rsi' => $rsi,
                    'macd' => $macd,
                ], [
                    'macd' => $latestMacd - $previousMacd,
                    'rsi' => $latestRsi - $previousRsi,
                ]);
            }
        }

        return [
            'buy' => $this->sortRecommendations($buy),
            'sell' => [],
        ];
    }

    /**
     * @param Collection<int, Stock> $stocks
     * @return array{buy: list<array<string, mixed>>, sell: list<array<string, mixed>>}
     */
    private function maEmaAdxRecommendations(Collection $stocks): array
    {
        $profile = config('nepse.recommendations.profiles.ma_ema_adx');
        $buy = [];
        $sell = [];

        foreach ($stocks as $stock) {
            $histories = $stock->priceHistories->sortBy('date')->values();

            if (! $this->shouldEvaluate($stock, $histories->count(), 'ma_ema_adx')) {
                continue;
            }

            [$high, $low, $close, $change, $hlc3] = $this->extractOhlc($histories, true);
            $adx = $this->indicators->adx($high, $low, $close, (int) $profile['adx_period']);
            $emaHigh = $this->indicators->ema($high, (int) $profile['ema_period']);
            $emaLow = $this->indicators->ema($low, (int) $profile['ema_period']);
            $emaHlc3 = $this->indicators->ema($hlc3, (int) $profile['ema_period']);

            if (! $this->hasRecentValues($adx) || ! $this->hasRecentValues($emaHigh) || ! $this->hasRecentValues($emaLow) || ! $this->hasRecentValues($emaHlc3)) {
                continue;
            }

            $latestAdx = $adx[array_key_last($adx)];
            $previousAdx = $this->previousValue($adx);
            $latestClose = $close[array_key_last($close)];
            $latestEmaHigh = $emaHigh[array_key_last($emaHigh)];
            $latestEmaLow = $emaLow[array_key_last($emaLow)];
            $latestChange = $change[array_key_last($change)];

            $deltas = [
                'adx' => $previousAdx !== null ? $latestAdx - $previousAdx : null,
            ];

            if ($latestAdx > (float) $profile['adx_min']
                && $latestAdx < (float) $profile['adx_max']
                && $latestClose > $latestEmaHigh
                && $latestChange > 0) {
                $buy[] = $this->buildRecommendationPayload($stock, $histories, [
                    'adx' => $adx,
                    'emaHigh' => $emaHigh,
                    'emaLow' => $emaLow,
                    'emaHlc3' => $emaHlc3,
                ], $deltas, round($latestEmaLow, 2));

                continue;
            }

            if ($latestClose < $latestEmaLow) {
                $sell[] = $this->buildRecommendationPayload($stock, $histories, [
                    'adx' => $adx,
                    'emaHigh' => $emaHigh,
                    'emaLow' => $emaLow,
                    'emaHlc3' => $emaHlc3,
                ], $deltas, round($latestEmaLow, 2));
            }
        }

        return [
            'buy' => $this->sortRecommendations($buy),
            'sell' => $this->sortRecommendations($sell),
        ];
    }

    private function shouldEvaluate(Stock $stock, int $historyCount, string $profileKey): bool
    {
        return $historyCount > (int) config("nepse.recommendations.profiles.{$profileKey}.minimum_history_points")
            && ! in_array($stock->sector?->name, config('nepse.recommendations.excluded_sector_names', []), true);
    }

    /**
     * @param \Illuminate\Support\Collection<int, PriceHistory> $histories
     * @return array{0: list<float>, 1: list<float>, 2: list<float>, 3?: list<float>, 4?: list<float>}
     */
    private function extractOhlc(\Illuminate\Support\Collection $histories, bool $withChange = false): array
    {
        $high = $histories->pluck('max_price')->map(fn ($value): float => (float) $value)->all();
        $low = $histories->pluck('min_price')->map(fn ($value): float => (float) $value)->all();
        $close = $histories->pluck('closing_price')->map(fn ($value): float => (float) $value)->all();

        if (! $withChange) {
            return [$high, $low, $close];
        }

        $change = $histories->pluck('change_percent')->map(fn ($value): float => (float) $value)->all();
        $hlc3 = $histories->pluck('hlc3')->map(fn ($value): float => (float) $value)->all();

        return [$high, $low, $close, $change, $hlc3];
    }

    /**
     * @param \Illuminate\Support\Collection<int, PriceHistory> $histories
     * @param array<string, list<float>> $metrics
     * @param array<string, float|null> $deltas
     * @return array<string, mixed>
     */
    private function buildRecommendationPayload(
        Stock $stock,
        \Illuminate\Support\Collection $histories,
        array $metrics,
        array $deltas,
        ?float $stopLoss = null,
    ): array {
        /** @var PriceHistory|null $asOfHistory */
        $asOfHistory = $histories->last();
        $latestHistory = $stock->latestPriceHistory;

        return [
            'symbol' => $stock->symbol,
            'companyName' => $stock->company_name,
            'sector' => $stock->sector?->name,
            'asOfDate' => $asOfHistory?->date?->toDateString(),
            'closeOnDate' => $asOfHistory?->closing_price,
            'closeToday' => $latestHistory?->closing_price ?? $asOfHistory?->closing_price,
            'stopLoss' => $stopLoss,
            'tradedSharePercent' => $this->tradedSharePercent($asOfHistory),
            'metrics' => collect($metrics)->map(fn (array $series): array => [
                'recent' => $this->recentValues($series, 3),
                'series' => $this->recentValues($series, (int) config('nepse.recommendations.sparkline_points')),
                'latest' => round((float) end($series), 4),
            ])->all(),
            'deltas' => collect($deltas)->map(
                fn (float|null $delta): ?float => $delta === null ? null : round($delta, 4),
            )->all(),
        ];
    }

    private function tradedSharePercent(?PriceHistory $history): ?float
    {
        if (! $history instanceof PriceHistory || $history->total_quantity <= 0) {
            return null;
        }

        return round(($history->traded_shares / $history->total_quantity) * 100, 4);
    }

    /**
     * @param list<float> $values
     * @return list<float>
     */
    private function recentValues(array $values, int $limit): array
    {
        return array_map(
            fn (float $value): float => round($value, 4),
            array_slice($values, $limit * -1),
        );
    }

    /**
     * @param list<array<string, mixed>> $recommendations
     * @return list<array<string, mixed>>
     */
    private function sortRecommendations(array $recommendations): array
    {
        usort($recommendations, function (array $left, array $right): int {
            $liquidityComparison = ($right['tradedSharePercent'] ?? 0) <=> ($left['tradedSharePercent'] ?? 0);

            if ($liquidityComparison !== 0) {
                return $liquidityComparison;
            }

            return strcmp($left['symbol'], $right['symbol']);
        });

        return $recommendations;
    }

    /**
     * @param array{buy: list<array<string, mixed>>, sell: list<array<string, mixed>>} $group
     */
    private function countSignals(array $group): int
    {
        return count($group['buy']) + count($group['sell']);
    }

    private function previousValue(array $values): ?float
    {
        return count($values) >= 2 ? $values[array_key_last($values) - 1] : null;
    }

    /**
     * @param list<float> $values
     */
    private function hasTwoRecentValues(array $values): bool
    {
        return count($values) >= 2;
    }

    /**
     * @param list<float> $values
     */
    private function hasRecentValues(array $values): bool
    {
        return count($values) >= 1;
    }

    /**
     * @return array{
     *     rsiAdx: array{buy: list<array<string, mixed>>, sell: list<array<string, mixed>>},
     *     rsiMacd: array{buy: list<array<string, mixed>>, sell: list<array<string, mixed>>},
     *     maEmaAdx: array{buy: list<array<string, mixed>>, sell: list<array<string, mixed>>}
     * }
     */
    private function emptyGroups(): array
    {
        return [
            'rsiAdx' => ['buy' => [], 'sell' => []],
            'rsiMacd' => ['buy' => [], 'sell' => []],
            'maEmaAdx' => ['buy' => [], 'sell' => []],
        ];
    }
}
