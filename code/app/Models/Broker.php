<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broker extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'broker_no',
        'broker_name',
    ];

    /**
     * @return HasMany<Floorsheet, $this>
     */
    public function buyFloorsheets(): HasMany
    {
        return $this->hasMany(Floorsheet::class, 'buyer_broker_id')->orderByDesc('trade_date');
    }

    /**
     * @return HasMany<Floorsheet, $this>
     */
    public function sellFloorsheets(): HasMany
    {
        return $this->hasMany(Floorsheet::class, 'seller_broker_id')->orderByDesc('trade_date');
    }
}
