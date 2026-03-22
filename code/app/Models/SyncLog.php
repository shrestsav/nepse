<?php

namespace App\Models;

use App\Enums\SyncMode;
use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    /** @use HasFactory<\Database\Factories\SyncLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'status',
        'batch_id',
        'start',
        'end',
        'total_time',
        'total_synced',
        'total_stocks',
        'processed_stocks',
        'error_summary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SyncMode::class,
            'status' => SyncStatus::class,
            'start' => 'immutable_datetime',
            'end' => 'immutable_datetime',
            'total_time' => 'integer',
            'total_synced' => 'integer',
            'total_stocks' => 'integer',
            'processed_stocks' => 'integer',
        ];
    }

    public function isRunning(): bool
    {
        return $this->status === SyncStatus::Queued || $this->status === SyncStatus::Running;
    }
}
