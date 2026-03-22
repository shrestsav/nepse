<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Stock extends Model
{
    /** @use HasFactory<\Database\Factories\StockFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sector_id',
        'symbol',
        'company_name',
    ];

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return HasMany<PriceHistory, $this>
     */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->orderByDesc('date');
    }

    /**
     * @return HasMany<PriceHistory, $this>
     */
    public function priceHistoriesAsc(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->orderBy('date');
    }

    /**
     * @return HasOne<PriceHistory, $this>
     */
    public function latestPriceHistory(): HasOne
    {
        return $this->hasOne(PriceHistory::class)->latestOfMany('date');
    }

    /**
     * @return HasMany<BacktestTrade, $this>
     */
    public function backtestTrades(): HasMany
    {
        return $this->hasMany(BacktestTrade::class);
    }
}
