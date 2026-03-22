<?php

namespace App\Services\Nepse;

use App\Enums\BacktestRunStatus;
use App\Enums\BacktestStrategy;
use App\Models\BacktestRun;
use App\Models\BacktestTrade;
use App\Models\PriceHistory;
use App\Models\Stock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class BacktestingService
{
    public function __construct(
        private readonly TechnicalIndicators $indicators,
    ) {
    }

    public function latestTradingDate(): ?CarbonImmutable
    {
        $date = PriceHistory::query()->max('date');

        return filled($date) ? CarbonImmutable::parse($date) : null;
    }

    public function earliestTradingDate(): ?CarbonImmutable
    {
        $date = PriceHistory::query()->min('date');

        return filled($date) ? CarbonImmutable::parse($date) : null;
    }

    public function defaultStartDate(?CarbonImmutable $endDate = null): ?CarbonImmutable
    {
        if (! $endDate instanceof CarbonImmutable) {
            return null;
        }

        $candidate = $endDate->subDays((int) config('nepse.backtesting.default_range_days', 365));
        $earliest = $this->earliestTradingDate();

        if ($earliest instanceof CarbonImmutable && $candidate->lt($earliest)) {
            return $earliest;
        }

        return $candidate;
    }

    public function run(BacktestRun $run): void
    {
        $run->forceFill([
            'status' => BacktestRunStatus::Running,
            'started_at' => $run->started_at ?? now(),
            'finished_at' => null,
            'error_summary' => null,
        ])->save();

        $stocks = $this->eligibleStocks($run);

        $run->forceFill([
            'eligible_stock_count' => $stocks->count(),
        ])->save();

        foreach ($stocks as $stock) {
            match ($run->strategy) {
                BacktestStrategy::RsiAdx => $this->runRsiAdx($run, $stock),
                BacktestStrategy::MaEmaAdx => $this->runMaEmaAdx($run, $stock),
            };
        }

        $this->refreshSummary($run);

        $run->forceFill([
            'status' => BacktestRunStatus::Completed,
            'finished_at' => now(),
        ])->save();
    }

    public function fail(BacktestRun $run, string $error): void
    {
        $run->forceFill([
            'status' => BacktestRunStatus::Failed,
            'started_at' => $run->started_at ?? now(),
            'finished_at' => now(),
            'error_summary' => trim(collect([$run->error_summary, $error])
                ->filter(fn (?string $line): bool => filled($line))
                ->implode("\n")),
        ])->save();
    }

    /**
     * @return Collection<int, Stock>
     */
    private function eligibleStocks(BacktestRun $run): Collection
    {
        $endDate = CarbonImmutable::parse($run->end_date->toDateString());
        $startDate = CarbonImmutable::parse($run->start_date->toDateString());
        $warmupPoints = (int) config('nepse.backtesting.warmup_points', 200);

        return Stock::query()
            ->with([
                'sector',
                'priceHistories' => fn ($query) => $query->whereDate('date', '<=', $endDate->toDateString()),
            ])
            ->get()
            ->filter(function (Stock $stock) use ($startDate, $warmupPoints): bool {
                if (in_array($stock->sector?->name, config('nepse.recommendations.excluded_sector_names', []), true)) {
                    return false;
                }

                return $this->windowHistories($stock->priceHistories, $startDate, $warmupPoints)->isNotEmpty();
            })
            ->values();
    }

    private function runRsiAdx(BacktestRun $run, Stock $stock): void
    {
        $profile = config('nepse.backtesting.profiles.rsi_adx');
        $startDate = CarbonImmutable::parse($run->start_date->toDateString());
        $endDate = CarbonImmutable::parse($run->end_date->toDateString());
        $warmupPoints = (int) config('nepse.backtesting.warmup_points', 200);
        $minimumHoldDays = (int) config('nepse.backtesting.minimum_hold_days', 3);
        $histories = $this->windowHistories($stock->priceHistories, $startDate, $warmupPoints);

        if ($histories->isEmpty()) {
            return;
        }

        [$high, $low, $close] = $this->extractOhlc($histories);
        $adx = $this->alignSeries(
            $this->indicators->adx($high, $low, $close, (int) $profile['adx_period']),
            $histories->count(),
            ((int) $profile['adx_period'] * 2) - 1,
        );
        $rsi = $this->alignSeries(
            $this->indicators->rsi($close, (int) $profile['rsi_period']),
            $histories->count(),
            (int) $profile['rsi_period'],
        );

        $openTrade = null;

        foreach ($histories as $index => $history) {
            if ($history->date->lt($startDate) || $history->date->gt($endDate)) {
                continue;
            }

            $adxToday = $adx[$index];
            $adxYesterday = $index > 0 ? $adx[$index - 1] : null;
            $rsiToday = $rsi[$index];
            $rsiYesterday = $index > 0 ? $rsi[$index - 1] : null;

            if ($adxToday === null || $adxYesterday === null || $rsiToday === null || $rsiYesterday === null) {
                continue;
            }

            if ($openTrade instanceof BacktestTrade) {
                if ($openTrade->buy_date->diffInDays($history->date) >= $minimumHoldDays
                    && $adxToday < $adxYesterday
                    && ($adxYesterday - $adxToday) > (float) $profile['adx_sell_drop_min']
                    && $rsiToday < $rsiYesterday) {
                    $this->closeTrade($openTrade, $history, 'signal');
                    $openTrade = null;
                }

                continue;
            }

            if ($adxToday > $adxYesterday
                && $adxToday > (float) $profile['adx_min']
                && $adxToday < (float) $profile['adx_max']
                && $rsiToday > $rsiYesterday
                && $rsiToday > (float) $profile['rsi_min']
                && $rsiToday < (float) $profile['rsi_max']) {
                $openTrade = $this->openTrade($run, $stock, $history, null, [
                    'adx_today' => round($adxToday, 4),
                    'adx_yesterday' => round($adxYesterday, 4),
                    'rsi_today' => round($rsiToday, 4),
                    'rsi_yesterday' => round($rsiYesterday, 4),
                ]);
            }
        }

        if ($openTrade instanceof BacktestTrade) {
            $closingHistory = $histories
                ->filter(fn (PriceHistory $history): bool => $history->date->gte($startDate) && $history->date->lte($endDate))
                ->last();

            if ($closingHistory instanceof PriceHistory) {
                $this->closeTrade($openTrade, $closingHistory, 'forced_close');
            }
        }
    }

    private function runMaEmaAdx(BacktestRun $run, Stock $stock): void
    {
        $profile = config('nepse.backtesting.profiles.ma_ema_adx');
        $startDate = CarbonImmutable::parse($run->start_date->toDateString());
        $endDate = CarbonImmutable::parse($run->end_date->toDateString());
        $warmupPoints = (int) config('nepse.backtesting.warmup_points', 200);
        $minimumHoldDays = (int) config('nepse.backtesting.minimum_hold_days', 3);
        $histories = $this->windowHistories($stock->priceHistories, $startDate, $warmupPoints);

        if ($histories->isEmpty()) {
            return;
        }

        [$high, $low, $close, $change, $hlc3] = $this->extractOhlc($histories, true);
        $adx = $this->alignSeries(
            $this->indicators->adx($high, $low, $close, (int) $profile['adx_period']),
            $histories->count(),
            ((int) $profile['adx_period'] * 2) - 1,
        );
        $emaHigh = $this->alignSeries(
            $this->indicators->ema($high, (int) $profile['ema_period']),
            $histories->count(),
            (int) $profile['ema_period'] - 1,
        );
        $emaLow = $this->alignSeries(
            $this->indicators->ema($low, (int) $profile['ema_period']),
            $histories->count(),
            (int) $profile['ema_period'] - 1,
        );
        $emaHlc3 = $this->alignSeries(
            $this->indicators->ema($hlc3, (int) $profile['ema_period']),
            $histories->count(),
            (int) $profile['ema_period'] - 1,
        );

        $openTrade = null;

        foreach ($histories as $index => $history) {
            if ($history->date->lt($startDate) || $history->date->gt($endDate)) {
                continue;
            }

            $adxToday = $adx[$index];
            $adxYesterday = $index > 0 ? $adx[$index - 1] : null;
            $emaHighToday = $emaHigh[$index];
            $emaLowToday = $emaLow[$index];
            $emaHlc3Today = $emaHlc3[$index];

            if ($adxToday === null || $adxYesterday === null || $emaHighToday === null || $emaLowToday === null || $emaHlc3Today === null) {
                continue;
            }

            if ($openTrade instanceof BacktestTrade) {
                $openTrade->forceFill([
                    'stop_loss' => round($emaLowToday, 2),
                ])->save();

                if ($openTrade->buy_date->diffInDays($history->date) >= $minimumHoldDays) {
                    if ($history->closing_price <= (float) $openTrade->stop_loss) {
                        $this->closeTrade($openTrade, $history, 'stop_loss');
                        $openTrade = null;

                        continue;
                    }

                    if ($history->closing_price < $emaLowToday) {
                        $this->closeTrade($openTrade, $history, 'signal');
                        $openTrade = null;
                    }
                }

                continue;
            }

            $priceAboveRatio = 1 - ($emaHighToday / max($history->closing_price, 0.0001));

            if ($adxToday > $adxYesterday
                && ($adxToday - $adxYesterday) > (float) $profile['adx_rise_min']
                && $adxToday > (float) $profile['adx_min']
                && $history->closing_price > $emaHighToday
                && $priceAboveRatio > (float) $profile['price_above_ema_high_min_ratio']
                && $history->change_percent > 0) {
                $openTrade = $this->openTrade($run, $stock, $history, round($emaLowToday, 2), [
                    'adx_today' => round($adxToday, 4),
                    'adx_yesterday' => round($adxYesterday, 4),
                    'ema_high' => round($emaHighToday, 4),
                    'ema_low' => round($emaLowToday, 4),
                    'ema_hlc3' => round($emaHlc3Today, 4),
                    'change_percent' => round((float) $history->change_percent, 4),
                ]);
            }
        }

        if ($openTrade instanceof BacktestTrade) {
            $closingHistory = $histories
                ->filter(fn (PriceHistory $history): bool => $history->date->gte($startDate) && $history->date->lte($endDate))
                ->last();

            if ($closingHistory instanceof PriceHistory) {
                $this->closeTrade($openTrade, $closingHistory, 'forced_close');
            }
        }
    }

    private function openTrade(
        BacktestRun $run,
        Stock $stock,
        PriceHistory $history,
        ?float $stopLoss,
        array $indicatorSnapshot,
    ): BacktestTrade {
        return BacktestTrade::query()->create([
            'backtest_run_id' => $run->id,
            'stock_id' => $stock->id,
            'symbol' => $stock->symbol,
            'buy_date' => $history->date,
            'buy_price' => round((float) $history->closing_price, 2),
            'sell_date' => $history->date,
            'sell_price' => round((float) $history->closing_price, 2),
            'stop_loss' => $stopLoss,
            'indicator_snapshot' => $indicatorSnapshot,
            'exit_reason' => 'open',
            'percentage_return' => 0,
            'holding_days' => 0,
        ]);
    }

    private function closeTrade(BacktestTrade $trade, PriceHistory $history, string $exitReason): void
    {
        $buyPrice = max((float) $trade->buy_price, 0.0001);
        $sellPrice = round((float) $history->closing_price, 2);
        $holdingDays = $trade->buy_date->diffInDays($history->date);

        $trade->forceFill([
            'sell_date' => $history->date,
            'sell_price' => $sellPrice,
            'exit_reason' => $exitReason,
            'percentage_return' => round((($sellPrice - $buyPrice) / $buyPrice) * 100, 4),
            'holding_days' => $holdingDays,
        ])->save();
    }

    private function refreshSummary(BacktestRun $run): void
    {
        $trades = $run->trades()->get();
        $wins = $trades->filter(fn (BacktestTrade $trade): bool => $trade->percentage_return > 0);
        $losses = $trades->filter(fn (BacktestTrade $trade): bool => $trade->percentage_return <= 0);

        $run->forceFill([
            'total_trades' => $trades->count(),
            'wins' => $wins->count(),
            'losses' => $losses->count(),
            'average_profit_rate' => round((float) $wins->avg('percentage_return'), 4),
            'average_loss_rate' => round((float) $losses->avg('percentage_return'), 4),
            'success_rate' => $trades->isEmpty()
                ? 0
                : round(($wins->count() / $trades->count()) * 100, 4),
        ])->save();
    }

    /**
     * @param EloquentCollection<int, PriceHistory> $histories
     * @return Collection<int, PriceHistory>
     */
    private function windowHistories(EloquentCollection $histories, CarbonImmutable $startDate, int $warmupPoints): Collection
    {
        $sorted = $histories->sortBy('date')->values();
        $startIndex = null;

        foreach ($sorted as $index => $history) {
            if ($history->date->gte($startDate)) {
                $startIndex = $index;

                break;
            }
        }

        if (! is_int($startIndex)) {
            return collect();
        }

        $sliceStart = max(0, $startIndex - $warmupPoints);

        return $sorted->slice($sliceStart)->values();
    }

    /**
     * @param Collection<int, PriceHistory> $histories
     * @return array{0: list<float>, 1: list<float>, 2: list<float>, 3?: list<float>, 4?: list<float>}
     */
    private function extractOhlc(Collection $histories, bool $withChange = false): array
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
     * @param list<float> $values
     * @return list<float|null>
     */
    private function alignSeries(array $values, int $length, int $offset): array
    {
        $aligned = array_fill(0, $length, null);

        foreach ($values as $index => $value) {
            $position = $offset + $index;

            if (array_key_exists($position, $aligned)) {
                $aligned[$position] = round($value, 4);
            }
        }

        return $aligned;
    }
}
