<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backtest_runs', function (Blueprint $table) {
            $table->id();
            $table->string('strategy');
            $table->string('status');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('eligible_stock_count')->default(0);
            $table->unsignedInteger('total_trades')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->decimal('average_profit_rate', 10, 4)->nullable();
            $table->decimal('average_loss_rate', 10, 4)->nullable();
            $table->decimal('success_rate', 10, 4)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtest_runs');
    }
};
