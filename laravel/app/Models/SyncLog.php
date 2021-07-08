<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
    ];

    protected $appends = ['type_is'];

    protected $fillable = ['type', 'type', 'start', 'end', 'total_time', 'total_synced'];

    public function getTypeIsAttribute()
    {
        return $this->type == 1 ? 'All Time' : 'Daily';
    }
}
