<?php

namespace App\Console\Commands;

use App\Enums\SyncMode;
use App\Enums\SyncStatus;
use App\Models\PriceHistory;
use App\Models\Stock;
use App\Models\SyncLog;
use App\Services\Nepse\MeroLaganiCatalogImporter;
use App\Services\Nepse\NepaliPaisaDailyPriceSynchronizer;
use App\Services\Nepse\SyncLogTracker;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class NepseSyncCommand extends Command
{
    /**
     * Daily:
     * `php artisan nepse:sync daily`
     * `php artisan nepse:sync daily --days=7`
     *
     * Full:
     * `php artisan nepse:sync full`
     * `php artisan nepse:sync full --symbol=ADBL`
     *
     * Smart:
     * `php artisan nepse:sync smart`
     */
    protected $signature = 'nepse:sync
        {mode=daily : Sync mode: daily, full, or smart}
        {--symbol=* : Limit daily or full sync to one or more symbols for debugging}
        {--from= : Override the starting trade date for daily or smart mode (YYYY-MM-DD)}
        {--to= : Override the ending trade date for daily or smart mode (YYYY-MM-DD)}
        {--days= : Number of days to include for daily mode}
        {--show-errors : Print date-level failures as they happen}';

    protected $description = 'Run NEPSE daily, full, or smart price syncs directly from the CLI';

    protected $help = <<<'HELP'
Usage:

  php artisan nepse:sync daily
  php artisan nepse:sync daily --days=7
  php artisan nepse:sync daily --from=2026-03-01 --to=2026-03-07

  php artisan nepse:sync full
  php artisan nepse:sync full --symbol=ADBL

  php artisan nepse:sync smart

Notes:

  - `daily` is the scheduled mode and refreshes the most recent market window.
  - `full` always starts from the configured full-sync date and does not accept --from, --to, or --days.
  - `smart` is fully automatic incremental sync and does not accept --from, --to, --days, or --symbol.
HELP;

    public function handle(
        MeroLaganiCatalogImporter $catalogImporter,
        NepaliPaisaDailyPriceSynchronizer $dailyPriceSynchronizer,
        SyncLogTracker $tracker,
    ): int {
        try {
            $mode = $this->resolveMode();
            $this->guardUnsupportedOptions($mode);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $syncLog = SyncLog::query()->create([
            'type' => $mode,
            'status' => SyncStatus::Queued,
            'start' => now(),
        ]);

        try {
            $tracker->markRunning($syncLog);

            $catalogCount = $catalogImporter->sync();
            $stocks = $this->resolveStocks();

            $syncLog->forceFill([
                'total_stocks' => $stocks->count(),
            ])->save();

            $dateRange = $this->resolveDateRange($mode, $stocks);

            $this->components->info(sprintf(
                'Starting %s NEPSE sync%s.',
                $mode->label(),
                $dateRange !== null
                    ? " from {$dateRange['start']->toDateString()} to {$dateRange['end']->toDateString()}"
                    : '',
            ));
            $this->components->info("Catalog synced for {$catalogCount} stock(s).");

            if ($this->selectedSymbols()->isNotEmpty()) {
                $this->components->info('Limiting sync to: '.$this->selectedSymbols()->implode(', '));
            }

            if ($stocks->isEmpty()) {
                $tracker->completeImmediately($syncLog, 0, 0);
                $this->components->warn('No stocks were available to sync.');

                return self::SUCCESS;
            }

            if ($dateRange === null) {
                $tracker->completeImmediately($syncLog, $stocks->count(), 0);
                $this->components->info('No trade dates required syncing for the requested smart range.');

                return self::SUCCESS;
            }

            return $mode === SyncMode::Full
                ? $this->runFullSync($syncLog, $stocks, $dateRange, $dailyPriceSynchronizer, $tracker)
                : $this->runDateBasedSync($syncLog, $stocks, $dateRange, $dailyPriceSynchronizer, $tracker);
        } catch (Throwable $throwable) {
            $tracker->fail($syncLog, $throwable->getMessage());
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param Collection<int, Stock> $stocks
     * @param array{start: CarbonImmutable, end: CarbonImmutable} $dateRange
     */
    private function runFullSync(
        SyncLog $syncLog,
        Collection $stocks,
        array $dateRange,
        NepaliPaisaDailyPriceSynchronizer $dailyPriceSynchronizer,
        SyncLogTracker $tracker,
    ): int {
        $selectedSymbols = $this->selectedSymbols();
        $period = CarbonPeriod::create($dateRange['start'], $dateRange['end']);
        $historyRows = 0;
        $syncedSymbols = [];
        $totalDays = $dateRange['start']->diffInDays($dateRange['end']) + 1;

        try {
            foreach ($period as $date) {
                $tradeDate = $date->toDateString();
                $result = $dailyPriceSynchronizer->syncTradeDate($tradeDate, $selectedSymbols);
                $historyRows += $result['rowsSynced'];

                if ($result['syncedSymbols'] === []) {
                    $this->line("{$tradeDate}: 0 row(s)");
                    continue;
                }

                foreach ($result['syncedSymbols'] as $symbol) {
                    $syncedSymbols[$symbol] = true;
                    $this->components->info("{$tradeDate} | {$symbol}: 1 row(s)");
                }

                $this->newLine();
            }
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();
            $this->components->error($message);
            $tracker->fail($syncLog, $message);

            return self::FAILURE;
        }

        $tracker->completeImmediately($syncLog, $stocks->count(), count($syncedSymbols));

        $fresh = $syncLog->fresh();

        $this->components->info(SyncMode::Full->label().' sync completed successfully.');
        $this->line("Stocks considered: {$fresh?->processed_stocks}/{$fresh?->total_stocks}");
        $this->line("Stocks with data: {$fresh?->total_synced}");
        $this->line("Trade dates processed: {$totalDays}");
        $this->line("History rows returned: {$historyRows}");

        return self::SUCCESS;
    }

    /**
     * @param Collection<int, Stock> $stocks
     * @param array{start: CarbonImmutable, end: CarbonImmutable} $dateRange
     */
    private function runDateBasedSync(
        SyncLog $syncLog,
        Collection $stocks,
        array $dateRange,
        NepaliPaisaDailyPriceSynchronizer $dailyPriceSynchronizer,
        SyncLogTracker $tracker,
    ): int {
        $period = CarbonPeriod::create($dateRange['start'], $dateRange['end']);
        $totalDays = $dateRange['start']->diffInDays($dateRange['end']) + 1;
        $progressBar = $this->output->createProgressBar($totalDays);
        $progressBar->start();

        $historyRows = 0;
        $syncedSymbols = [];

        foreach ($period as $date) {
            $tradeDate = $date->toDateString();

            try {
                $result = $dailyPriceSynchronizer->syncTradeDate($tradeDate, $this->selectedSymbols());
                $historyRows += $result['rowsSynced'];

                foreach ($result['syncedSymbols'] as $symbol) {
                    $syncedSymbols[$symbol] = true;
                }

                if ($this->output->isVerbose()) {
                    $this->newLine();
                    $this->line("{$tradeDate}: {$result['rowsSynced']} row(s)");
                }
            } catch (Throwable $throwable) {
                $message = "{$tradeDate}: {$throwable->getMessage()}";

                if ($this->output->isVerbose() || $this->option('show-errors')) {
                    $this->newLine();
                    $this->components->error($message);
                }

                $progressBar->finish();
                $this->newLine(2);
                $tracker->fail($syncLog, $message);

                return self::FAILURE;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $tracker->completeImmediately(
            $syncLog,
            $stocks->count(),
            count($syncedSymbols),
        );

        $fresh = $syncLog->fresh();

        $this->components->info($syncLog->type->label().' sync completed successfully.');
        $this->line("Stocks considered: {$fresh?->processed_stocks}/{$fresh?->total_stocks}");
        $this->line("Stocks with data: {$fresh?->total_synced}");
        $this->line("Trade dates processed: {$totalDays}");
        $this->line("History rows returned: {$historyRows}");

        return self::SUCCESS;
    }

    private function resolveMode(): SyncMode
    {
        return match (strtolower((string) $this->argument('mode'))) {
            'daily' => SyncMode::Daily,
            'full' => SyncMode::Full,
            'smart' => SyncMode::Smart,
            default => throw new RuntimeException('Invalid sync mode. Use one of: daily, full, smart.'),
        };
    }

    /**
     * @param Collection<int, Stock> $stocks
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    private function resolveDateRange(SyncMode $mode, Collection $stocks): ?array
    {
        $endDate = ($this->option('to') !== null && $mode !== SyncMode::Full)
            ? $this->parseDateOption('to')
            : CarbonImmutable::today();

        $startDate = match ($mode) {
            SyncMode::Daily => $this->resolveDailyStartDate($endDate),
            SyncMode::Full => CarbonImmutable::parse((string) config('nepse.sync.full_from_date')),
            SyncMode::Smart => $this->resolveSmartStartDate($stocks),
            default => throw new RuntimeException('Unsupported CLI sync mode.'),
        };

        if ($startDate->greaterThan($endDate)) {
            if ($mode === SyncMode::Smart && $this->option('from') === null && $this->option('to') === null) {
                return null;
            }

            throw new RuntimeException('The sync start date must be on or before the end date.');
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    private function guardUnsupportedOptions(SyncMode $mode): void
    {
        if ($mode === SyncMode::Full && ($this->option('from') !== null || $this->option('to') !== null || $this->option('days') !== null)) {
            throw new RuntimeException('Full sync always starts from the configured full-sync date and does not accept --from, --to, or --days.');
        }

        if ($mode === SyncMode::Smart && ($this->option('from') !== null || $this->option('to') !== null || $this->option('days') !== null || $this->selectedSymbols()->isNotEmpty())) {
            throw new RuntimeException('Smart sync is fully automatic and does not accept --from, --to, --days, or --symbol.');
        }
    }

    private function resolveDailyStartDate(CarbonImmutable $endDate): CarbonImmutable
    {
        if ($this->option('from') !== null) {
            return $this->parseDateOption('from');
        }

        $days = (int) ($this->option('days') ?? config('nepse.sync.daily_lookback_days', 7));

        if ($days < 1) {
            throw new RuntimeException('The --days option must be at least 1.');
        }

        return $endDate->subDays($days - 1);
    }

    /**
     * @param Collection<int, Stock> $stocks
     */
    private function resolveSmartStartDate(Collection $stocks): CarbonImmutable
    {
        if ($this->option('from') !== null) {
            return $this->parseDateOption('from');
        }

        $fallback = CarbonImmutable::parse((string) config('nepse.sync.full_from_date'));

        if ($stocks->isEmpty()) {
            return $fallback;
        }

        $latestDates = PriceHistory::query()
            ->selectRaw('stock_id, MAX(date) as latest_date')
            ->whereIn('stock_id', $stocks->modelKeys())
            ->groupBy('stock_id')
            ->pluck('latest_date', 'stock_id');

        return $stocks
            ->map(function (Stock $stock) use ($fallback, $latestDates): CarbonImmutable {
                $latestDate = $latestDates->get($stock->id);

                if ($latestDate === null) {
                    return $fallback;
                }

                return CarbonImmutable::parse((string) $latestDate)->addDay();
            })
            ->min() ?? $fallback;
    }

    private function parseDateOption(string $option): CarbonImmutable
    {
        $value = trim((string) $this->option($option));

        if ($value === '') {
            throw new RuntimeException("The --{$option} option cannot be empty.");
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable $throwable) {
            throw new RuntimeException("The --{$option} option must be a valid date.", previous: $throwable);
        }
    }

    /**
     * @return Collection<int, Stock>
     */
    private function resolveStocks(): Collection
    {
        $query = Stock::query()->orderBy('symbol');
        $symbols = $this->selectedSymbols();

        if ($symbols->isNotEmpty()) {
            $query->whereIn('symbol', $symbols->all());
        }

        return $query->get();
    }

    /**
     * @return Collection<int, string>
     */
    private function selectedSymbols(): Collection
    {
        return collect($this->option('symbol'))
            ->map(fn (mixed $symbol): string => strtoupper(trim((string) $symbol)))
            ->filter()
            ->values();
    }
}
