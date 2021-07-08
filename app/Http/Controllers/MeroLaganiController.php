<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Goutte\Client;
use App\Models\Stock;
use App\Models\PriceHistory;
use Illuminate\Http\Request;

class MeroLaganiController extends Controller
{   
    private $results = [];

    private $dailyPriceAttributes = ['symbol', 'LTP', 'change_percent', 'high', 'low', 'open', 'quantity'];
    
    public function livePrice()
    {
        $dateToday = Carbon::now()->format('Y-m-d');
        
        $this->results = [];

        $client = new Client();

        $crawler = $client->request('GET', 'https://merolagani.com/LatestMarket.aspx');

        $crawler->filterXPath('//table[@class="table table-hover live-trading sortable"]')
            ->filter('tbody tr')
            ->each(function ($tr, $i) { 
                $tr->filter('td')->each(function ($td, $i) { 
                    if($i < 7)
                        $this->stock[$this->dailyPriceAttributes[$i]] = trim($td->text());
                }); 
                array_push($this->results, $this->stock); 
            });

        foreach($this->results as $result){
            $company = Stock::where('symbol', $result['symbol']);

            if($company->exists()){
                $company = $company->first();
                
                $priceHistory = PriceHistory::updateOrCreate(
                    ['stock_id' => $company->id, 'date' => $dateToday],
                    [
                        'stock_id'           => $company->id,
                        'date'               => $dateToday,
                        'closing_price'      => str_replace(",", "", $result['LTP']),
                        'max_price'          => str_replace(",", "", $result['high']),
                        'min_price'          => str_replace(",", "", $result['low']),
                        'change'             => 0,
                        'change_percent'     => $result['change_percent'],
                        'previous_closing'   => 0,
                        'traded_shares'      => str_replace(",", "", $result['quantity']),
                        'traded_amount'      => 0,
                        'total_quantity'     => 0,
                        'total_transaction'  => 0,
                        'total_amount'       => 0,
                        'no_of_transactions' => 0
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Successful',
            'type'    => 'Live Sync'
        ]);
    }
}
