<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floorsheets', function (Blueprint $table) {
            $table->id();
            $table->string('transaction')->unique();
            $table->date('trade_date');
            $table->string('symbol');
            $table->foreignId('stock_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('buyer_broker_no');
            $table->string('seller_broker_no');
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
            $table->unsignedBigInteger('quantity');
            $table->decimal('rate', 12, 2);
            $table->decimal('amount', 16, 2);
            $table->timestamps();

            $table->index('trade_date');
            $table->index('symbol');
            $table->index('stock_id');
            $table->index('buyer_broker_id');
            $table->index('seller_broker_id');
            $table->index(['trade_date', 'symbol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floorsheets');
    }
};
