<?php

namespace App\Models;

use App\Enums\BacktestRunStatus;
use App\Enums\BacktestStrategy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BacktestRun extends Model
{
    /** @use HasFactory<\Database\Factories\BacktestRunFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'strategy',
        'status',
        'start_date',
        'end_date',
        'started_at',
        'finished_at',
        'eligible_stock_count',
        'total_trades',
        'wins',
        'losses',
        'average_profit_rate',
        'average_loss_rate',
        'success_rate',
        'error_summary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strategy' => BacktestStrategy::class,
            'status' => BacktestRunStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'eligible_stock_count' => 'integer',
            'total_trades' => 'integer',
            'wins' => 'integer',
            'losses' => 'integer',
            'average_profit_rate' => 'float',
            'average_loss_rate' => 'float',
            'success_rate' => 'float',
        ];
    }

    /**
     * @return HasMany<BacktestTrade, $this>
     */
    public function trades(): HasMany
    {
        return $this->hasMany(BacktestTrade::class);
    }

    public function isRunning(): bool
    {
        return $this->status === BacktestRunStatus::Queued || $this->status === BacktestRunStatus::Running;
    }
}
