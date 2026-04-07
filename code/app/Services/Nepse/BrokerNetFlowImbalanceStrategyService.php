<?php

namespace App\Services\Nepse;

use App\Models\PriceHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BrokerNetFlowImbalanceStrategyService
{
    private const SIGNAL_BUY = 'buy';

    private const SIGNAL_SELL = 'sell';

    private const SIGNAL_NEUTRAL = 'neutral';

    public function earliestTradeDate(): ?CarbonImmutable
    {
        $date = DB::table('aggregated_floorsheet')->min('trade_date');

        return filled($date) ? CarbonImmutable::parse($date) : null;
    }

    public function latestTradeDate(): ?CarbonImmutable
    {
        $date = DB::table('aggregated_floorsheet')->max('trade_date');

        return filled($date) ? CarbonImmutable::parse($date) : null;
    }

    public function resolveTradeDate(?string $requestedDate = null): ?CarbonImmutable
    {
        $query = DB::table('aggregated_floorsheet');

        if (filled($requestedDate)) {
            $query->whereDate('trade_date', '<=', $requestedDate);
        }

        $date = $query->max('trade_date');

        return filled($date) ? CarbonImmutable::parse($date) : null;
    }

    /**
     * @return array{
     *     summary: array{
     *         symbolsScanned: int,
     *         symbolsPassingTurnover: int,
     *         buyCandidates: int,
     *         sellCandidates: int,
     *         neutral: int
     *     },
     *     rows: list<array{
     *         symbol: string,
     *         stockId: int|null,
     *         close: float|null,
     *         changePercent: float|null,
     *         turnover: float,
     *         netFlowTop5: float,
     *         netFlowRatio: float,
     *         buyerBrokers: int,
     *         sellerBrokers: int,
     *         dominanceRatio: float,
     *         signal: string,
     *         topBuyers: list<array{brokerNo: string, amount: float}>,
     *         topSellers: list<array{brokerNo: string, amount: float}>
     *     }>
     * }
     */
    public function analyze(
        CarbonImmutable $tradeDate,
        float $minTurnover,
        int $limit,
        float $netFlowRatioThreshold,
        float $dominanceRatioThreshold,
    ): array {
        $tradeDateString = $tradeDate->toDateString();

        $baseRows = DB::table('aggregated_floorsheet')
            ->selectRaw('
                symbol,
                MAX(stock_id) as stock_id,
                SUM(total_amount) as turnover,
                COUNT(DISTINCT buyer_broker_no) as buyer_brokers,
                COUNT(DISTINCT seller_broker_no) as seller_brokers
            ')
            ->whereDate('trade_date', $tradeDateString)
            ->groupBy('symbol')
            ->get()
            ->map(fn (object $row): array => [
                'symbol' => (string) $row->symbol,
                'stock_id' => $row->stock_id !== null ? (int) $row->stock_id : null,
                'turnover' => round((float) $row->turnover, 2),
                'buyer_brokers' => (int) $row->buyer_brokers,
                'seller_brokers' => (int) $row->seller_brokers,
            ])
            ->values();

        $symbolsScanned = $baseRows->count();
        $passingTurnover = $baseRows
            ->filter(fn (array $row): bool => (float) $row['turnover'] >= $minTurnover)
            ->values();
        $symbolsPassing = $passingTurnover->pluck('symbol')->all();

        if ($symbolsPassing === []) {
            return [
                'summary' => [
                    'symbolsScanned' => $symbolsScanned,
                    'symbolsPassingTurnover' => 0,
                    'buyCandidates' => 0,
                    'sellCandidates' => 0,
                    'neutral' => 0,
                ],
                'rows' => [],
            ];
        }

        $buyBySymbol = DB::table('aggregated_floorsheet')
            ->selectRaw('symbol, buyer_broker_no as broker_no, SUM(total_amount) as buy_amount')
            ->whereDate('trade_date', $tradeDateString)
            ->whereIn('symbol', $symbolsPassing)
            ->groupBy('symbol', 'buyer_broker_no')
            ->get();

        $sellBySymbol = DB::table('aggregated_floorsheet')
            ->selectRaw('symbol, seller_broker_no as broker_no, SUM(total_amount) as sell_amount')
            ->whereDate('trade_date', $tradeDateString)
            ->whereIn('symbol', $symbolsPassing)
            ->groupBy('symbol', 'seller_broker_no')
            ->get();

        $brokerFlowsBySymbol = [];
        $buyersBySymbol = [];
        $sellersBySymbol = [];

        foreach ($buyBySymbol as $row) {
            $symbol = (string) $row->symbol;
            $brokerNo = (string) $row->broker_no;
            $amount = round((float) $row->buy_amount, 2);

            $brokerFlowsBySymbol[$symbol] ??= [];
            $brokerFlowsBySymbol[$symbol][$brokerNo] ??= ['buy' => 0.0, 'sell' => 0.0];
            $brokerFlowsBySymbol[$symbol][$brokerNo]['buy'] += $amount;
            $buyersBySymbol[$symbol][$brokerNo] = $amount;
        }

        foreach ($sellBySymbol as $row) {
            $symbol = (string) $row->symbol;
            $brokerNo = (string) $row->broker_no;
            $amount = round((float) $row->sell_amount, 2);

            $brokerFlowsBySymbol[$symbol] ??= [];
            $brokerFlowsBySymbol[$symbol][$brokerNo] ??= ['buy' => 0.0, 'sell' => 0.0];
            $brokerFlowsBySymbol[$symbol][$brokerNo]['sell'] += $amount;
            $sellersBySymbol[$symbol][$brokerNo] = $amount;
        }

        $pricesBySymbol = PriceHistory::query()
            ->join('stocks', 'stocks.id', '=', 'price_histories.stock_id')
            ->whereDate('price_histories.date', $tradeDateString)
            ->whereIn('stocks.symbol', $symbolsPassing)
            ->get([
                'stocks.symbol as symbol',
                'price_histories.closing_price as close_price',
                'price_histories.change_percent as change_percent',
            ])
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->symbol => [
                    'close' => $row->close_price !== null ? round((float) $row->close_price, 2) : null,
                    'changePercent' => $row->change_percent !== null ? round((float) $row->change_percent, 2) : null,
                ],
            ]);

        $rows = $passingTurnover
            ->map(function (array $baseRow) use (
                $brokerFlowsBySymbol,
                $buyersBySymbol,
                $sellersBySymbol,
                $pricesBySymbol,
                $netFlowRatioThreshold,
                $dominanceRatioThreshold,
            ): array {
                $symbol = $baseRow['symbol'];
                $turnover = max((float) $baseRow['turnover'], 0.0);
                $brokerFlows = collect($brokerFlowsBySymbol[$symbol] ?? [])
                    ->map(function (array $amounts, string $brokerNo): array {
                        $buyAmount = round((float) $amounts['buy'], 2);
                        $sellAmount = round((float) $amounts['sell'], 2);
                        $netAmount = round($buyAmount - $sellAmount, 2);

                        return [
                            'brokerNo' => $brokerNo,
                            'buyAmount' => $buyAmount,
                            'sellAmount' => $sellAmount,
                            'netAmount' => $netAmount,
                        ];
                    })
                    ->sortByDesc(fn (array $flow): float => abs((float) $flow['netAmount']))
                    ->values();

                $top5Net = round((float) $brokerFlows->take(5)->sum('netAmount'), 2);
                $maxAbsoluteNet = round((float) $brokerFlows
                    ->map(fn (array $flow): float => abs((float) $flow['netAmount']))
                    ->max(), 2);
                $netFlowRatio = $turnover > 0 ? round($top5Net / $turnover, 4) : 0.0;
                $dominanceRatio = $turnover > 0 ? round($maxAbsoluteNet / $turnover, 4) : 0.0;
                $price = $pricesBySymbol->get($symbol);
                $changePercent = is_array($price) ? $price['changePercent'] : null;
                $signal = $this->resolveSignal(
                    $netFlowRatio,
                    $changePercent,
                    $dominanceRatio,
                    $netFlowRatioThreshold,
                    $dominanceRatioThreshold,
                );

                return [
                    'symbol' => $symbol,
                    'stockId' => $baseRow['stock_id'],
                    'close' => is_array($price) ? $price['close'] : null,
                    'changePercent' => $changePercent,
                    'turnover' => round($turnover, 2),
                    'netFlowTop5' => $top5Net,
                    'netFlowRatio' => $netFlowRatio,
                    'buyerBrokers' => (int) $baseRow['buyer_brokers'],
                    'sellerBrokers' => (int) $baseRow['seller_brokers'],
                    'dominanceRatio' => $dominanceRatio,
                    'signal' => $signal,
                    'topBuyers' => collect($buyersBySymbol[$symbol] ?? [])
                        ->map(fn (float $amount, string $brokerNo): array => [
                            'brokerNo' => $brokerNo,
                            'amount' => round($amount, 2),
                        ])
                        ->sortByDesc('amount')
                        ->take(5)
                        ->values()
                        ->all(),
                    'topSellers' => collect($sellersBySymbol[$symbol] ?? [])
                        ->map(fn (float $amount, string $brokerNo): array => [
                            'brokerNo' => $brokerNo,
                            'amount' => round($amount, 2),
                        ])
                        ->sortByDesc('amount')
                        ->take(5)
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        $summary = [
            'symbolsScanned' => $symbolsScanned,
            'symbolsPassingTurnover' => $rows->count(),
            'buyCandidates' => $rows->where('signal', self::SIGNAL_BUY)->count(),
            'sellCandidates' => $rows->where('signal', self::SIGNAL_SELL)->count(),
            'neutral' => $rows->where('signal', self::SIGNAL_NEUTRAL)->count(),
        ];

        $rankedRows = $rows
            ->sort(function (array $left, array $right): int {
                $absoluteRatioComparison = abs($right['netFlowRatio']) <=> abs($left['netFlowRatio']);

                if ($absoluteRatioComparison !== 0) {
                    return $absoluteRatioComparison;
                }

                $turnoverComparison = $right['turnover'] <=> $left['turnover'];

                if ($turnoverComparison !== 0) {
                    return $turnoverComparison;
                }

                return $left['symbol'] <=> $right['symbol'];
            })
            ->take($limit)
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'rows' => $rankedRows,
        ];
    }

    private function resolveSignal(
        float $netFlowRatio,
        ?float $changePercent,
        float $dominanceRatio,
        float $netFlowRatioThreshold,
        float $dominanceRatioThreshold,
    ): string {
        if ($changePercent === null) {
            return self::SIGNAL_NEUTRAL;
        }

        if ($netFlowRatio >= $netFlowRatioThreshold
            && $changePercent > 0
            && $dominanceRatio <= $dominanceRatioThreshold) {
            return self::SIGNAL_BUY;
        }

        if ($netFlowRatio <= -$netFlowRatioThreshold
            && $changePercent < 0
            && $dominanceRatio <= $dominanceRatioThreshold) {
            return self::SIGNAL_SELL;
        }

        return self::SIGNAL_NEUTRAL;
    }
}
