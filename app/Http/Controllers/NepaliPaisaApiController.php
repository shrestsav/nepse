<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\PriceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NepaliPaisaApiController extends Controller
{
    private $fromDate = '2021-07-04';

    public function testho(Request $request){
        $response = Http::withHeaders([
                        'Content-Type' => 'application/json; charset=UTF-8'
                    ])->post('https://nepalipaisa.com/Modules/CompanyProfile/webservices/CompanyService.asmx/GetCompanyPriceHistory', [
                        'StockSymbol' => 'CIT',
                        'FromDate' => $this->fromDate,
                        'ToDate' => date('Y-m-d'),
                        'Offset' => 1,
                        'Limit' => 3000,
                    ]);

        return ($response->json());
    }

    public function priceHistory($symbol){
        $history = Http::withHeaders([
                        'Content-Type' => 'application/json; charset=UTF-8'
                    ])->post('https://nepalipaisa.com/Modules/CompanyProfile/webservices/CompanyService.asmx/GetCompanyPriceHistory', [
                        'StockSymbol' => $symbol,
                        'FromDate' => $this->fromDate,
                        'ToDate' => date('Y-m-d'),
                        'Offset' => 1,
                        'Limit' => 3000,
                    ]);

        return $history->json()['d'];
    }

    public function getPriceHistory(Request $request)
    {
        $symbols = $request->symbols;
        
        foreach($symbols as $symbol){
            $company = Stock::where('symbol', $symbol);
            
            if($company->exists()){
                $company = $company->first();
                $histories = $this->priceHistory($symbol);

                foreach($histories as $history){
                    $priceHistory = PriceHistory::updateOrCreate(
                        ['stock_id' => $company->id, 'date' => $history['AsOfDateShortString']],
                        [
                            'stock_id'           => $company->id,
                            'date'               => $history['AsOfDateShortString'],
                            'closing_price'      => $history['ClosingPrice'],
                            'max_price'          => $history['MaxPrice'],
                            'min_price'          => $history['MinPrice'],
                            'change'             => $history['Difference'],
                            'change_percent'     => $history['PercentDifference'],
                            'previous_closing'   => $history['PreviousClosing'],
                            'traded_shares'      => $history['TradedShares'],
                            'traded_amount'      => $history['TradedAmount'],
                            'total_quantity'     => str_replace(",", "", $history['TotalQuantity']),
                            'total_transaction'  => str_replace(",", "", $history['TotalTransaction']),
                            'total_amount'       => str_replace(",", "", $history['TotalAmount']),
                            'no_of_transactions' => $history['NoOfTransaction']
                        ]
                    );
                }
            }
            
        }

        return response()->json([
            'message' => 'Successful'
        ]);
    }
}
