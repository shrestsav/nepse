<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    /** @use HasFactory<\Database\Factories\PriceHistoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = [
        'hlc3',
    ];

    /**
     * @var list<string>
     */
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
        'no_of_transactions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'closing_price' => 'float',
            'max_price' => 'float',
            'min_price' => 'float',
            'change' => 'float',
            'change_percent' => 'float',
            'previous_closing' => 'float',
            'traded_shares' => 'integer',
            'traded_amount' => 'integer',
            'total_quantity' => 'integer',
            'total_transaction' => 'integer',
            'total_amount' => 'float',
            'no_of_transactions' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Stock, $this>
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function getHlc3Attribute(): float
    {
        return round(($this->max_price + $this->min_price + $this->closing_price) / 3, 4);
    }
}
