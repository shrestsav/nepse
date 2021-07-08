<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

     /**
     * Get the stocks for the sector.
     */
    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }
}
