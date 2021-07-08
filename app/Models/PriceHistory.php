<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    use HasFactory;

    protected $appends = ['hlc3'];

    protected $fillable = [
        'stock_id',
        'date',
        'closing_price',
        'max_price',
        'min_price',
        'change',
        'change_percent',
        'previous_closing',
        'traded_shares',
        'traded_amount',
        'total_quantity',
        'total_transaction',
        'total_amount',
        'no_of_transactions'
    ];

    /**
     * Get the average of high, low, and closing price
     */
    public function getHlc3Attribute()
    {
        return ($this->max_price + $this->min_price + $this->closing_price)/3;
    }
}
