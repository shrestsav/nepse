<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aggregated_floorsheet', function (Blueprint $table) {
            $table->id();
            $table->date('trade_date');
            $table->string('symbol', 20);
            $table->foreignId('stock_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('buyer_broker_no', 20);
            $table->string('seller_broker_no', 20);
            $table->foreignId('buyer_broker_id')
                ->nullable()
                ->constrained('brokers')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('seller_broker_id')
                ->nullable()
                ->constrained('brokers')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->decimal('rate', 12, 2);
            $table->unsignedInteger('transaction_count');
            $table->unsignedBigInteger('total_quantity');
            $table->decimal('total_amount', 18, 2);
            $table->timestamps();

            $table->index(['trade_date', 'symbol'], 'aggregated_floorsheet_trade_date_symbol_idx');
            $table->index(
                ['trade_date', 'buyer_broker_no', 'seller_broker_no'],
                'aggregated_floorsheet_trade_date_broker_idx',
            );
            $table->unique(
                ['trade_date', 'symbol', 'stock_id', 'seller_broker_no', 'buyer_broker_no', 'rate'],
                'aggregated_floorsheet_group_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregated_floorsheet');
    }
};
