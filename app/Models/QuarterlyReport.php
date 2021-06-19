<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuarterlyReport extends Model
{
    use HasFactory;

    protected $fillable = ['stock_id' ,'fiscal_year' ,'EPS' ,'PE_ratio' ,'book_value' ,'PBV'];
}
