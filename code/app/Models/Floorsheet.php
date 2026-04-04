<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Floorsheet extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'transaction',
        'trade_date',
        'symbol',
        'stock_id',
        'buyer_broker_no',
        'seller_broker_no',
        'buyer_broker_id',
        'seller_broker_id',
        'quantity',
        'rate',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trade_date' => 'date',
            'stock_id' => 'integer',
            'buyer_broker_id' => 'integer',
            'seller_broker_id' => 'integer',
            'quantity' => 'integer',
            'rate' => 'float',
            'amount' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Stock, $this>
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * @return BelongsTo<Broker, $this>
     */
    public function buyerBroker(): BelongsTo
    {
        return $this->belongsTo(Broker::class, 'buyer_broker_id');
    }

    /**
     * @return BelongsTo<Broker, $this>
     */
    public function sellerBroker(): BelongsTo
    {
        return $this->belongsTo(Broker::class, 'seller_broker_id');
    }
}
