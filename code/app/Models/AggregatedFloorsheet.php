<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AggregatedFloorsheet extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $table = 'aggregated_floorsheet';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trade_date',
        'symbol',
        'stock_id',
        'buyer_broker_no',
        'seller_broker_no',
        'buyer_broker_id',
        'seller_broker_id',
        'rate',
        'transaction_count',
        'total_quantity',
        'total_amount',
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
            'rate' => 'float',
            'transaction_count' => 'integer',
            'total_quantity' => 'integer',
            'total_amount' => 'float',
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
