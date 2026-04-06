<?php

namespace App\Services\Nepse;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FloorsheetAggregator
{
    public function rebuildTradeDate(string $tradeDate): int
    {
        $normalizedTradeDate = CarbonImmutable::parse($tradeDate)->toDateString();

        return DB::transaction(function () use ($normalizedTradeDate): int {
            DB::table('aggregated_floorsheet')
                ->whereDate('trade_date', $normalizedTradeDate)
                ->delete();

            DB::table('aggregated_floorsheet')->insertUsing(
                [
                    'trade_date',
                    'symbol',
                    'stock_id',
                    'buyer_broker_no',
                    'seller_broker_no',
                    'buyer_broker_id',
                    'seller_broker_id',
                    'rate',
                    'transaction_count',
                    'total_quantity',
                    'total_amount',
                    'created_at',
                    'updated_at',
                ],
                DB::table('floorsheets')
                    ->selectRaw('
                        trade_date,
                        symbol,
                        stock_id,
                        buyer_broker_no,
                        seller_broker_no,
                        MIN(buyer_broker_id) as buyer_broker_id,
                        MIN(seller_broker_id) as seller_broker_id,
                        rate,
                        COUNT(*) as transaction_count,
                        SUM(quantity) as total_quantity,
                        SUM(amount) as total_amount,
                        CURRENT_TIMESTAMP as created_at,
                        CURRENT_TIMESTAMP as updated_at
                    ')
                    ->whereDate('trade_date', $normalizedTradeDate)
                    ->groupBy([
                        'trade_date',
                        'symbol',
                        'stock_id',
                        'buyer_broker_no',
                        'seller_broker_no',
                        'rate',
                    ])
            );

            return (int) DB::table('aggregated_floorsheet')
                ->whereDate('trade_date', $normalizedTradeDate)
                ->count();
        });
    }
}
