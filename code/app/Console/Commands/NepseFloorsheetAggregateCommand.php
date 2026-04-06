<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SendsTelegramNotifications;
use App\Services\Nepse\FloorsheetAggregator;
use App\Services\Notifications\TelegramNotifier;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class NepseFloorsheetAggregateCommand extends Command
{
    use SendsTelegramNotifications;

    protected $signature = 'nepse:floorsheet-aggregate
        {--date= : Trade date to aggregate (YYYY-MM-DD)}
        {--from= : Start date for a rebuild range (YYYY-MM-DD)}
        {--to= : End date for a rebuild range (YYYY-MM-DD)}';

    protected $description = 'Aggregate NEPSE floorsheet rows from the raw floorsheets table';

    public function handle(
        FloorsheetAggregator $aggregator,
        TelegramNotifier $telegramNotifier,
    ): int
    {
        try {
            $tradeDates = $this->resolveTradeDates();
            $firstTradeDate = $tradeDates[0];
            $lastTradeDate = $tradeDates[count($tradeDates) - 1];
            $rowsAggregated = 0;

            $this->components->info(sprintf(
                'Starting NEPSE floorsheet aggregation%s.',
                count($tradeDates) === 1
                    ? " for {$firstTradeDate}"
                    : " from {$firstTradeDate} to {$lastTradeDate}",
            ));

            foreach ($tradeDates as $tradeDate) {
                $rowsAggregated += $aggregator->rebuildTradeDate($tradeDate);
            }

            $this->components->info('Floorsheet aggregation completed successfully.');
            $this->line('Trade dates processed: '.count($tradeDates));
            $this->line("Date range: {$firstTradeDate} to {$lastTradeDate}");
            $this->line("Aggregated rows rebuilt: {$rowsAggregated}");

            $this->sendTelegramSummary(
                $telegramNotifier,
                'NEPSE Floorsheet Aggregate',
                true,
                [
                    'Trade Dates Processed: '.count($tradeDates),
                    "Date Range: {$firstTradeDate} to {$lastTradeDate}",
                    "Aggregated Rows Rebuilt: {$rowsAggregated}",
                ],
            );

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());
            $this->sendTelegramSummary(
                $telegramNotifier,
                'NEPSE Floorsheet Aggregate',
                false,
                ['Error: '.$throwable->getMessage()],
            );

            return self::FAILURE;
        }
    }

    /**
     * @return list<string>
     */
    private function resolveTradeDates(): array
    {
        $timezone = (string) config('app.timezone');
        $date = $this->option('date');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($date !== null && ($from !== null || $to !== null)) {
            throw new RuntimeException('Use either --date or --from/--to, not both.');
        }

        if ($date !== null) {
            return [$this->parseDateOption((string) $date, $timezone)->toDateString()];
        }

        if ($to !== null && $from === null) {
            throw new RuntimeException('The --to option requires --from.');
        }

        if ($from === null) {
            $endDate = CarbonImmutable::today($timezone);
            $startDate = $endDate->subDays(6);

            return collect(CarbonPeriod::create($startDate, $endDate))
                ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())
                ->values()
                ->all();
        }

        $startDate = $this->parseDateOption((string) $from, $timezone);
        $endDate = $to !== null
            ? $this->parseDateOption((string) $to, $timezone)
            : CarbonImmutable::today($timezone);

        if ($startDate->greaterThan($endDate)) {
            throw new RuntimeException('The sync start date must be on or before the end date.');
        }

        return collect(CarbonPeriod::create($startDate, $endDate))
            ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())
            ->values()
            ->all();
    }

    private function parseDateOption(string $date, string $timezone): CarbonImmutable
    {
        try {
            $parsedDate = CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone);

            if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
                throw new RuntimeException('The provided date must use the YYYY-MM-DD format.');
            }

            return $parsedDate;
        } catch (Throwable) {
            throw new RuntimeException('The provided date must use the YYYY-MM-DD format.');
        }
    }
}
