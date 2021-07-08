<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Stock;
use Laratrade\Trader\Facades\Trader;
use App\Http\Controllers\MeroLaganiController;

class TraderController extends Controller
{
    private $buyStocks = [];

    private $fromDate = '2021-01-01';

    private $excludedStockTypes = ['Mutual Fund', 'Promotor Share', 'Preferred Stock', 'Corporate Debenture'];

    public function getRecommendationsByRsiNAdx()
    {
        $this->buyStocks = [];

        $stocks = Stock::with([
            'sector',
            'priceHistory' => function ($query) {
                $query->where('date', '>', $this->fromDate);
            }
        ])->get();
        
        foreach ($stocks as $key => $stock) {
            $priceHistory = $stock->priceHistory;
            
            if (count($priceHistory) > 50 && !in_array($stock->sector->name,  $this->excludedStockTypes)) {
                $high = array_reverse($priceHistory->pluck('max_price')->toArray());
                $low = array_reverse($priceHistory->pluck('min_price')->toArray());
                $close = array_reverse($priceHistory->pluck('closing_price')->toArray());

                $adx = Trader::adx($high, $low, $close, 14);
                $rsi = Trader::rsi($close, 14);

                $reverse_adx = array_reverse($adx);
                $reverse_rsi = array_reverse($rsi);

                $adx_today = $reverse_adx[0];
                $adx_yesterday = $reverse_adx[1];

                $rsi_today = $reverse_rsi[0];
                $rsi_yesterday = $reverse_rsi[1];

                if (
                    $adx_today > $adx_yesterday &&
                    $adx_today > 23 &&
                    $rsi_today > $rsi_yesterday &&
                    $rsi_today > 50
                ) {
                    $this->buyStocks[$stock->symbol] = [
                        'stock' => [
                            'company_name' => $stock->company_name,
                            'symbol' => $stock->symbol,
                        ],
                        'reverse_RSI' => $reverse_rsi,
                        'reverse_ADX' => $reverse_adx,
                    ];
                }
            }
        }

        return response()->json($this->buyStocks);;
    }

    public function getRecommendationsByRsiNMacd()
    {
        $recommendations = [];

        $stocks = Stock::with([
            'sector',
            'priceHistory' => function ($query) {
                $query->where('date', '>', $this->fromDate);
            }
        ])->get();

        foreach ($stocks as $key => $stock) {
            $priceHistory = $stock->priceHistory;

            if (count($priceHistory) > 50 && !in_array($stock->sector->name,  $this->excludedStockTypes)) {
                $high = array_reverse($priceHistory->pluck('max_price')->toArray());
                $low = array_reverse($priceHistory->pluck('min_price')->toArray());
                $close = array_reverse($priceHistory->pluck('closing_price')->toArray());

                $MACD = Trader::macd($close, 12, 26, 9)[0];
                $RSI = Trader::rsi($close, 14);

                $reverse_MACD = array_reverse($MACD);
                $reverse_RSI = array_reverse($RSI);

                $MACD_today = $reverse_MACD[0];
                $MACD_yesterday = $reverse_MACD[1];

                $RSI_today = $reverse_RSI[0];
                $RSI_yesterday = $reverse_RSI[1];

                if (
                    $MACD_today > $MACD_yesterday &&
                    $RSI_today > $RSI_yesterday &&
                    $RSI_today > 50
                ) {
                    $recommendations[$stock->symbol] = [
                        'stock' => [
                            'company_name' => $stock->company_name,
                            'symbol' => $stock->symbol,
                        ],
                        'reverse_RSI' => $reverse_RSI,
                        'reverse_MACD' => $reverse_MACD,
                    ];
                }
            }
        }

        return response()->json($recommendations);;
    }

    public function getRecommendationsByMaEmaAdx()
    {
        //test
        // $merolagani = new MeroLaganiController;
        // $merolagani->livePrice();
        
        $recommendations = [];

        $stocks = Stock::with([
            'sector',
            'priceHistory' => function ($query) {
                $query->where('date', '>', $this->fromDate)->where('date', '<=', '2021-07-08');
            }
        ])->get();

        foreach ($stocks as $key => $stock) {
            $priceHistory = $stock->priceHistory;
            
            if (count($priceHistory) > 50 && !in_array($stock->sector->name,  $this->excludedStockTypes)) {
                $tillDate = $priceHistory->pluck('date')->toArray()[0];
                $change = $priceHistory->pluck('change_percent')->toArray();

                $high = array_reverse($priceHistory->pluck('max_price')->toArray());
                $low = array_reverse($priceHistory->pluck('min_price')->toArray());
                $close = array_reverse($priceHistory->pluck('closing_price')->toArray());
                $hlc3 = array_reverse($priceHistory->pluck('hlc3')->toArray());
                
                $ADX = Trader::adx($high, $low, $close, 5);
                $EMA_high = Trader::ema($high, 10);
                $EMA_low = Trader::ema($low, 10);
                $EMA_hlc3 = Trader::ema($hlc3, 10);
                
                $reverse_ADX = array_reverse($ADX);
                $reverse_EMA_high = array_reverse($EMA_high);
                $reverse_EMA_low = array_reverse($EMA_low);
                $reverse_EMA_hlc3 = array_reverse($EMA_hlc3);
                
                $close_today = $close[count($close)-1];
                $change_today = $change[0];
                
                $ADX_today = $reverse_ADX[0];
                $ADX_yesterday = $reverse_ADX[1];

                $EMA_high_today = $reverse_EMA_high[0];
                $EMA_high_yesterday = $reverse_EMA_high[1];
                
                $EMA_low_today = $reverse_EMA_low[0];
                $EMA_low_yesterday = $reverse_EMA_low[1];

                $EMA_hlc3_today = $reverse_EMA_hlc3[0];
                $EMA_hlc3_yesterday = $reverse_EMA_hlc3[1];

                if (
                    $ADX_today > 40 &&
                    $close_today > $EMA_high_today &&
                    $change_today > 0
                ) {
                    $recommendations[$stock->symbol] = [
                        'stock' => [
                            'company_name' => $stock->company_name,
                            'symbol'       => $stock->symbol,
                        ],
                        'close_today'      => $close_today,
                        'tillDate'         => $tillDate,
                        'reverse_ADX'      => $reverse_ADX,
                        'reverse_EMA_high' => $reverse_EMA_high,
                        'reverse_EMA_hlc3' => $reverse_EMA_hlc3,
                        'reverse_EMA_low'  => $reverse_EMA_low,
                    ];
                }
            }
        }

        return response()->json($recommendations);;
    }

    public function heikinAshiCandle()
    {
        $dateBeforeSevenDays = Carbon::now()->subDays(7)->format('Y-m-d');

        $heikinAshiPriceHistory = [];

        $priceHistory = Stock::with([
            'sector',
            'priceHistory' => function ($query){
                $query->limit(7);
            }
        ])->where('symbol','CLBSL')->first()->priceHistory;

        // foreach($stocks as $stock){

            foreach($priceHistory as $key => $history){
                $data = [
                    'date'    => $history['date'],
                    'HAOpen'  => $this->heikinAshiOpen(),
                    'HAClose' => $this->heikinAshiClose(),
                    'HAHigh'  => $this->heikinAshiHigh(),
                    'HALow'   => $this->heikinAshiLow()
                ];
    
                $heikinAshiPriceHistory[] = $data;
            }
        // }

        return $heikinAshiPriceHistory;
    }

    public function heikinAshiOpen()
    {
        
    }

    public function heikinAshiClose()
    {
        
    }

    public function heikinAshiHigh()
    {

    }
    
    public function heikinAshiLow()
    {

    }

    public function test()
    {
        $priceHistory = Stock::where('symbol', 'ADBL')->first()->priceHistory;

        $real = array_reverse($priceHistory->pluck('closing_price')->toArray());

        $result = Trader::trima($real, 30);

        $result = Trader::bbands($real, 20, 2.0, 2.0, 0);

        $highBand = array_reverse($result[0]);
        $midBand = array_reverse($result[1]);
        $lowBand = array_reverse($result[2]);

        $result = [
            [
                $highBand[0], $midBand[0], $lowBand[0]
            ],
            [
                $highBand[1], $midBand[1], $lowBand[1]
            ],
            [
                $highBand[2], $midBand[2], $lowBand[2]
            ],
        ];
        return $result;
    }
}
