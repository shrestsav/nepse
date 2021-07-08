<?php

namespace App\Http\Controllers;

use Goutte\Client;
use App\Models\Stock;
use App\Models\Sector;
use App\Models\SyncLog;
use App\Models\PriceHistory;
use Illuminate\Http\Request;
use App\Models\QuarterlyReport;
use Illuminate\Support\Facades\Storage;
Use Exception; 

class NepseScrapingController extends Controller
{
    private $results = [];
    private $stock = [];
    private $attributes = ['symbol', 'LTP', 'LTV', 'change_percent', 'high', 'low', 'open', 'quantity'];
    private $dailyPriceAttributes = ['symbol', 'LTP', 'change_percent', 'high', 'low', 'open', 'quantity'];

    //Scrape All Stocks from merolagani.com
    public function scrape()
    {
        $client = new Client();

        $crawler = $client->request('GET', 'https://merolagani.com/LatestMarket.aspx');

        $crawler->filterXPath('//table[@class="table table-hover live-trading sortable"]')
            ->filter('tbody tr')
            ->each(function ($tr, $i) { 
                // if($i < 1){
                    $tr->filter('td')->each(function ($td, $i) { 
                        if($i == 0){
                            $clientt = new Client();
                            $getdetailsPage = $clientt->click($td->filter('a')->link());
                            $detailsPage = $getdetailsPage->filter('tbody tr')->each(function ($tr, $i) { 
                                try {
                                    $attr = strtolower(str_replace(' ', '_', $tr->filter('th')->text()));
                                    $this->stock[$attr] = $tr->filter('td')->text();
                                } catch(Exception $e) {
                                }
                            });
                            $getdetailsPage->filter('.table.table-striped.table-hover.table-zeromargin')->each(function ($tr, $i) { 
                                if($i == 4){
                                    $tr->filter('tr td')->each(function ($td, $j) { 
                                        if($j == 1)
                                            $this->stock['company_name'] = $td->text();
                                    });
                                }
                                    
                            });
                        }
                        if($i < 8)
                            $this->stock[$this->attributes[$i]] = trim($td->text());
                    }); 
                    array_push($this->results, $this->stock); 
                // }
            });

        $contents = json_encode($this->results);

        Storage::put('scrape.json', $contents);

        return $this->results;
    }

    // Initialize Stocks data from scrape.json from merolagani.com
    public function initialize()
    {
        $path = storage_path(). "/app/scrape.json";
        $stocks = json_decode(file_get_contents($path), true); 

        foreach($stocks as $stock){
            //Store Sector
            $checkSector = Sector::where('name', $stock['sector']);
            
            if(!$checkSector->exists()){
                $sector = Sector::create([
                    'name' => $stock['sector']
                ]);
            }
            else{
                $sector = $checkSector->first();
            }
            
            // Store Stock
            $checkStock = Stock::where('symbol', $stock['symbol']);

            if(!$checkStock->exists()){
                $company = Stock::create([
                    'sector_id'    => $sector->id,
                    'symbol'       => $stock['symbol'],
                    'company_name' => $stock['company_name'],
                ]);
            }
            else{
                $company = $checkStock->first();
            }

            //Store Quarterly Report
            if(!QuarterlyReport::where('fiscal_year', $stock['eps'])->exists()){
                $report = QuarterlyReport::create([
                    'stock_id'    => $company->id,
                    'fiscal_year' => $stock['eps'],
                    'EPS'         => 1.98,
                    'PE_ratio'    => str_replace(',', '', $stock['p/e_ratio']),
                    'book_value'  => str_replace(',', '', $stock['book_value']),
                    'PBV'         => str_replace(',', '', $stock['pbv']),
                ]);
            }
        }

        return 'done';
    }

    public function priceHistory($symbol)
    {
        $client = new Client();

        $this->results = [];
        
        $crawler = $client->request('GET', 'https://nepalstockinfo.com/companyhistory/' . $symbol);

        $crawler->filterXPath('//table[@class="table table-bordered stripe row-border order-column example_datatable_fixedcolumn"]')
            ->each(function ($node, $i) {
                if($i == 0){
                    $node->filter('tbody tr')
                        ->each(function ($tr, $i) { 
                            $tr->filter('td')->each(function ($td, $i) { 
                                $this->stock[$i] = $td->text();
                            }); 
                            array_push($this->results, $this->stock); 
                        });
                }
            });

        return $this->results;
    }

    public function getPriceHistory(Request $request)
    {
        $symbols = $request->symbols;
        
        $priceHistories = [];

        foreach($symbols as $symbol){
            $company = Stock::where('symbol', $symbol);

            if($company->exists()){
                $company = $company->first();
                $histories = $this->priceHistory($symbol);
                $priceHistories[$symbol] = $histories;

                foreach($histories as $history){
                    $priceHistoryAlready = PriceHistory::where('stock_id',$company->id)->where('date', $history[1]);
                    
                    if(!$priceHistoryAlready->exists()){
                        $priceHistory = PriceHistory::create([
                            'stock_id'       => $company->id,
                            'date'           => $history[1],
                            'LTP'            => $history[2],
                            'change'         => $history[3],
                            'change_percent' => 0,
                            'high'           => $history[4],
                            'low'            => $history[5],
                            'traded_shares'  => $history[8],
                            'traded_amount'  => $history[9]
                        ]);
                    }
                }
            }
            
        }

        return $priceHistories;
    }

    public function getPriceForCurrentDay()
    {
        return Stock::where('symbol','CLBSL')->with('priceHistory')->get();
    }
}
