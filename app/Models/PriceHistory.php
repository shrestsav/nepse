<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'date',
        'LTP',
        'change',
        'change_percent',
        'high',
        'low',
        'quantity',
        'turnover'
    ];
}
