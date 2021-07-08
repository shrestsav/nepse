<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function getAllStocks()
    {
        $stocks = Stock::all();

        return response()->json($stocks);
    }
}
