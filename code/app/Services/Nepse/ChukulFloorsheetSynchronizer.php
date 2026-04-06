<?php

namespace App\Services\Nepse;

use App\Models\Broker;
use App\Models\Floorsheet;
use App\Models\Stock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ChukulFloorsheetSynchronizer
{
    private ?Collection $stocksBySymbol = null;

    private ?Collection $brokersByNumber = null;

    public function __construct(
        private readonly MeroLaganiCatalogImporter $catalogImporter,
        private readonly ChukulBrokerSynchronizer $brokerSynchronizer,
    ) {}

    public function refreshReferenceData(): int
    {
        $brokersSynced = $this->brokerSynchronizer->sync();
        $this->reloadStocks();

        if ($this->stocksBySymbol->isEmpty()) {
            $this->refreshStockCatalog();
        }

        $this->reloadBrokers();

        return $brokersSynced;
    }

    /**
     * @param  null|callable(array{
     *     tradeDate: string,
     *     page: int,
     *     pageRows: int,
     *     pageRowsSynced: int,
     *     pageUnresolvedStocks: int,
     *     pageUnresolvedBrokers: int,
     *     totalRowsSynced: int,
     *     totalUnresolvedStocks: int,
     *     totalUnresolvedBrokers: int,
     *     isLastPage: bool
     * }): void  $onPageFetched
     * @return array{
     *     brokersSynced: int,
     *     pagesFetched: int,
     *     rowsSynced: int,
     *     unresolvedStocks: int,
     *     unresolvedBrokers: int
     * }
     */
    public function syncTradeDate(string $tradeDate, bool $refreshReferences = true, ?callable $onPageFetched = null): array
    {
        $normalizedTradeDate = CarbonImmutable::parse($tradeDate)->toDateString();

        $brokersSynced = $refreshReferences ? $this->refreshReferenceData() : 0;
        $stocksBySymbol = $this->stocksBySymbol ?? collect();
        $brokersByNumber = $this->brokersByNumber ?? collect();

        $pageSize = max(1, (int) config('nepse.chukul.floorsheet_page_size', 500));
        $page = 1;
        $pagesFetched = 0;
        $rowsSynced = 0;
        $unresolvedStocks = 0;
        $unresolvedBrokers = 0;
        $catalogRefreshedDuringRun = false;

        while (true) {
            $payload = $this->fetchPage($normalizedTradeDate, $page, $pageSize);
            $rows = $payload['rows'];
            $pagesFetched++;
            $pageRows = $rows->count();
            $pageRowsSynced = 0;
            $pageUnresolvedStocks = 0;
            $pageUnresolvedBrokers = 0;

            if ($rows->isEmpty()) {
                $onPageFetched?->__invoke([
                    'tradeDate' => $normalizedTradeDate,
                    'page' => $page,
                    'pageRows' => 0,
                    'pageRowsSynced' => 0,
                    'pageUnresolvedStocks' => 0,
                    'pageUnresolvedBrokers' => 0,
                    'totalRowsSynced' => $rowsSynced,
                    'totalUnresolvedStocks' => $unresolvedStocks,
                    'totalUnresolvedBrokers' => $unresolvedBrokers,
                    'isLastPage' => true,
                ]);
                break;
            }

            if (! $catalogRefreshedDuringRun && $this->pageContainsUnknownSymbols($rows, $stocksBySymbol)) {
                $this->refreshStockCatalog();
                $stocksBySymbol = $this->stocksBySymbol ?? collect();
                $catalogRefreshedDuringRun = true;
            }

            foreach ($rows as $row) {
                $stock = $stocksBySymbol->get($row['symbol']);
                $buyerBroker = $row['buyer_broker_no'] !== ''
                    ? $brokersByNumber->get($row['buyer_broker_no'])
                    : null;
                $sellerBroker = $row['seller_broker_no'] !== ''
                    ? $brokersByNumber->get($row['seller_broker_no'])
                    : null;

                if (! $stock instanceof Stock) {
                    $unresolvedStocks++;
                    $pageUnresolvedStocks++;
                }

                if ($row['buyer_broker_no'] !== '' && ! $buyerBroker instanceof Broker) {
                    $unresolvedBrokers++;
                    $pageUnresolvedBrokers++;
                }

                if ($row['seller_broker_no'] !== '' && ! $sellerBroker instanceof Broker) {
                    $unresolvedBrokers++;
                    $pageUnresolvedBrokers++;
                }

                Floorsheet::query()->updateOrCreate(
                    ['transaction' => $row['transaction']],
                    [
                        'trade_date' => $normalizedTradeDate,
                        'symbol' => $row['symbol'],
                        'stock_id' => $stock?->id,
                        'buyer_broker_no' => $row['buyer_broker_no'],
                        'seller_broker_no' => $row['seller_broker_no'],
                        'buyer_broker_id' => $buyerBroker?->id,
                        'seller_broker_id' => $sellerBroker?->id,
                        'quantity' => $row['quantity'],
                        'rate' => $row['rate'],
                        'amount' => $row['amount'],
                    ],
                );

                $rowsSynced++;
                $pageRowsSynced++;
            }

            $isLastPage = $pageRows < $pageSize;

            $onPageFetched?->__invoke([
                'tradeDate' => $normalizedTradeDate,
                'page' => $page,
                'pageRows' => $pageRows,
                'pageRowsSynced' => $pageRowsSynced,
                'pageUnresolvedStocks' => $pageUnresolvedStocks,
                'pageUnresolvedBrokers' => $pageUnresolvedBrokers,
                'totalRowsSynced' => $rowsSynced,
                'totalUnresolvedStocks' => $unresolvedStocks,
                'totalUnresolvedBrokers' => $unresolvedBrokers,
                'isLastPage' => $isLastPage,
            ]);

            if ($isLastPage) {
                break;
            }

            $page++;
        }

        return [
            'brokersSynced' => $brokersSynced,
            'pagesFetched' => $pagesFetched,
            'rowsSynced' => $rowsSynced,
            'unresolvedStocks' => $unresolvedStocks,
            'unresolvedBrokers' => $unresolvedBrokers,
        ];
    }

    /**
     * @return array{
     *     rows: Collection<int, array{
     *         transaction: string,
     *         symbol: string,
     *         buyer_broker_no: string,
     *         seller_broker_no: string,
     *         quantity: int,
     *         rate: float,
     *         amount: float
     *     }>
     * }
     */
    private function fetchPage(string $tradeDate, int $page, int $pageSize): array
    {
        $response = Http::timeout(30)
            ->retry(3, fn (int $attempt): int => $attempt * 1000, throw: false)
            ->acceptJson()
            ->withHeaders($this->requestHeaders())
            ->get((string) config('nepse.chukul.floorsheet_url'), [
                'date' => $tradeDate,
                'page' => $page,
                'size' => $pageSize,
            ])
            ->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException("Unexpected floorsheet response for [{$tradeDate}] page [{$page}].");
        }

        $rows = $payload;

        if (array_key_exists('data', $payload)) {
            $rows = $payload['data'];
        }

        if (! is_array($rows)) {
            throw new RuntimeException("Unexpected floorsheet response for [{$tradeDate}] page [{$page}].");
        }

        return [
            'rows' => collect($rows)
                ->map(function (mixed $row) use ($tradeDate, $page): array {
                    if (! is_array($row)) {
                        throw new RuntimeException("Floorsheet response contained an invalid row for [{$tradeDate}] page [{$page}].");
                    }

                    return $this->normalizeRow($row, $tradeDate, $page);
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     transaction: string,
     *     symbol: string,
     *     buyer_broker_no: string,
     *     seller_broker_no: string,
     *     quantity: int,
     *     rate: float,
     *     amount: float
     * }
     */
    private function normalizeRow(array $row, string $tradeDate, int $page): array
    {
        $transaction = trim((string) ($row['transaction'] ?? ''));
        $symbol = strtoupper(trim((string) ($row['symbol'] ?? '')));
        $buyerBrokerNo = trim((string) ($row['buyer'] ?? ''));
        $sellerBrokerNo = trim((string) ($row['seller'] ?? ''));

        if ($transaction === '' || $symbol === '') {
            throw new RuntimeException("Floorsheet response contained a malformed row for [{$tradeDate}] page [{$page}].");
        }

        return [
            'transaction' => $transaction,
            'symbol' => $symbol,
            'buyer_broker_no' => $buyerBrokerNo,
            'seller_broker_no' => $sellerBrokerNo,
            'quantity' => $this->toInt($row['quantity'] ?? null, 'quantity', $tradeDate, $page),
            'rate' => $this->toFloat($row['rate'] ?? null, 'rate', $tradeDate, $page),
            'amount' => $this->toFloat($row['amount'] ?? null, 'amount', $tradeDate, $page),
        ];
    }

    private function reloadStocks(): void
    {
        $this->stocksBySymbol = Stock::query()
            ->get(['id', 'symbol'])
            ->keyBy('symbol');
    }

    private function reloadBrokers(): void
    {
        $this->brokersByNumber = Broker::query()
            ->get(['id', 'broker_no'])
            ->keyBy('broker_no');
    }

    private function refreshStockCatalog(): void
    {
        $this->catalogImporter->sync();
        $this->reloadStocks();
    }

    private function pageContainsUnknownSymbols(Collection $rows, Collection $stocksBySymbol): bool
    {
        return $rows->contains(fn (array $row): bool => ! $stocksBySymbol->has($row['symbol']));
    }

    private function toFloat(mixed $value, string $field, string $tradeDate, int $page): float
    {
        $normalized = trim(str_replace(',', '', (string) $value));

        if (! is_numeric($normalized)) {
            throw new RuntimeException("Floorsheet row field [{$field}] was invalid for [{$tradeDate}] page [{$page}].");
        }

        return round((float) $normalized, 2);
    }

    private function toInt(mixed $value, string $field, string $tradeDate, int $page): int
    {
        $normalized = trim(str_replace(',', '', (string) $value));

        if (! is_numeric($normalized)) {
            throw new RuntimeException("Floorsheet row field [{$field}] was invalid for [{$tradeDate}] page [{$page}].");
        }

        return (int) round((float) $normalized);
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Priority' => 'u=1, i',
            'Referer' => 'https://chukul.com/floorsheet',
            'Sec-CH-UA' => '"Chromium";v="146", "Not-A.Brand";v="24", "Google Chrome";v="146"',
            'Sec-CH-UA-Mobile' => '?0',
            'Sec-CH-UA-Platform' => '"macOS"',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
        ];
    }
}
