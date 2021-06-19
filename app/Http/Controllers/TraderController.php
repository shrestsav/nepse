<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;
use Laratrade\Trader\Facades\Trader;

class TraderController extends Controller
{
    private $buyStocks = [];

    public function calculate()
    {
        $this->buyStocks = [];

        $stocks = Stock::all();
        
        foreach($stocks as $key => $stock){
            $priceHistory = $stock->priceHistory;

            if(count($priceHistory) > 14){
                $high = array_reverse($priceHistory->pluck('high')->toArray());
                $low = array_reverse($priceHistory->pluck('low')->toArray());
                $close = array_reverse($priceHistory->pluck('LTP')->toArray());

                $adx = Trader::adx($high, $low, $close, 14);
                $rsi = Trader::rsi($close, 14);
                // $macd = Trader::macd($close, 12, 26, 9);

                $reverse_adx = array_reverse($adx);
                $reverse_rsi = array_reverse($rsi);

                $adx_today = $reverse_adx[0];
                $adx_yesterday = $reverse_adx[1];

                $rsi_today = $reverse_rsi[0];
                $rsi_yesterday = $reverse_rsi[1];

                if($adx_today > $adx_yesterday && $adx_today > 23 && $adx_today < 30 && $rsi_today > $rsi_yesterday && $rsi_today > 50){
                    // $this->buyStocks[$stock->symbol] = [
                    //     'RSI' => $reverse_rsi,
                    //     'ADX' => $reverse_adx
                    // ];
                    array_push($this->buyStocks, $stock->symbol);
                }
            }
            
        }
        // $priceHistory = Stock::where('symbol','ADBL')->first()->priceHistory;

        
        return $this->buyStocks;
        return array_reverse($rsi);
    }

    public function test()
    {
        $priceHistory = Stock::where('symbol','ADBL')->first()->priceHistory;

        $real = array_reverse($priceHistory->pluck('LTP')->toArray());

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
