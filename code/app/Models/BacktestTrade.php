<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacktestTrade extends Model
{
    /** @use HasFactory<\Database\Factories\BacktestTradeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'backtest_run_id',
        'stock_id',
        'symbol',
        'buy_date',
        'buy_price',
        'sell_date',
        'sell_price',
        'stop_loss',
        'indicator_snapshot',
        'exit_reason',
        'percentage_return',
        'holding_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'buy_date' => 'date',
            'buy_price' => 'float',
            'sell_date' => 'date',
            'sell_price' => 'float',
            'stop_loss' => 'float',
            'indicator_snapshot' => 'array',
            'percentage_return' => 'float',
            'holding_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BacktestRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(BacktestRun::class, 'backtest_run_id');
    }

    /**
     * @return BelongsTo<Stock, $this>
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
