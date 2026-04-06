<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SendsTelegramNotifications;
use App\Services\Nepse\ChukulFloorsheetSynchronizer;
use App\Services\Notifications\TelegramNotifier;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class NepseFloorsheetSyncCommand extends Command
{
    use SendsTelegramNotifications;

    protected $signature = 'nepse:floorsheet-sync
        {--date= : Trade date to sync (YYYY-MM-DD)}
        {--from= : Start date for a backfill range (YYYY-MM-DD)}
        {--to= : End date for a backfill range (YYYY-MM-DD)}';

    protected $description = 'Sync NEPSE broker data and floorsheet trades from Chukul';

    public function handle(
        ChukulFloorsheetSynchronizer $synchronizer,
        TelegramNotifier $telegramNotifier,
    ): int
    {
        try {
            $tradeDates = $this->resolveTradeDates();
            $firstTradeDate = $tradeDates[0];
            $lastTradeDate = $tradeDates[count($tradeDates) - 1];

            $this->components->info(sprintf(
                'Starting NEPSE floorsheet sync%s.',
                count($tradeDates) === 1
                    ? " for {$firstTradeDate}"
                    : " from {$firstTradeDate} to {$lastTradeDate}",
            ));

            $brokersSynced = $synchronizer->refreshReferenceData();
            $summary = [
                'pagesFetched' => 0,
                'rowsSynced' => 0,
                'unresolvedStocks' => 0,
                'unresolvedBrokers' => 0,
            ];

            foreach ($tradeDates as $tradeDate) {
                $result = $synchronizer->syncTradeDate(
                    $tradeDate,
                    refreshReferences: false,
                    onPageFetched: function (array $page): void {
                        $suffix = $page['isLastPage'] ? ' (last page)' : '';

                        $this->line(sprintf(
                            '[%s] page %d: fetched %d row(s), synced %d row(s), unresolved stocks %d, unresolved brokers %d%s',
                            $page['tradeDate'],
                            $page['page'],
                            $page['pageRows'],
                            $page['pageRowsSynced'],
                            $page['pageUnresolvedStocks'],
                            $page['pageUnresolvedBrokers'],
                            $suffix,
                        ));
                    },
                );
                $summary['pagesFetched'] += $result['pagesFetched'];
                $summary['rowsSynced'] += $result['rowsSynced'];
                $summary['unresolvedStocks'] += $result['unresolvedStocks'];
                $summary['unresolvedBrokers'] += $result['unresolvedBrokers'];
            }

            $this->components->info('Floorsheet sync completed successfully.');
            $this->line('Trade dates processed: '.count($tradeDates));
            $this->line("Date range: {$firstTradeDate} to {$lastTradeDate}");
            $this->line("Brokers synced: {$brokersSynced}");
            $this->line("Pages fetched: {$summary['pagesFetched']}");
            $this->line("Floorsheet rows imported/updated: {$summary['rowsSynced']}");
            $this->line("Unresolved stock count: {$summary['unresolvedStocks']}");
            $this->line("Unresolved broker count: {$summary['unresolvedBrokers']}");

            $this->sendTelegramSummary(
                $telegramNotifier,
                'NEPSE Floorsheet Sync',
                true,
                [
                    'Trade Dates Processed: '.count($tradeDates),
                    "Date Range: {$firstTradeDate} to {$lastTradeDate}",
                    "Brokers Synced: {$brokersSynced}",
                    "Pages Fetched: {$summary['pagesFetched']}",
                    "Rows Imported/Updated: {$summary['rowsSynced']}",
                    "Unresolved Stocks: {$summary['unresolvedStocks']}",
                    "Unresolved Brokers: {$summary['unresolvedBrokers']}",
                ],
            );

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());
            $this->sendTelegramSummary(
                $telegramNotifier,
                'NEPSE Floorsheet Sync',
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
            return [CarbonImmutable::today($timezone)->toDateString()];
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
