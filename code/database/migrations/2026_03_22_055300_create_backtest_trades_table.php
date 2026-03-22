<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backtest_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backtest_run_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained()->nullOnDelete();
            $table->string('symbol');
            $table->date('buy_date');
            $table->decimal('buy_price', 10, 2);
            $table->date('sell_date');
            $table->decimal('sell_price', 10, 2);
            $table->decimal('stop_loss', 10, 2)->nullable();
            $table->json('indicator_snapshot')->nullable();
            $table->string('exit_reason');
            $table->decimal('percentage_return', 10, 4);
            $table->unsignedInteger('holding_days')->default(0);
            $table->timestamps();

            $table->index(['backtest_run_id', 'sell_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtest_trades');
    }
};
