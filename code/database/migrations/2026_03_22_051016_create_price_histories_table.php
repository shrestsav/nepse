<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id');
            $table->date('date');
            $table->decimal('closing_price', 10, 2);
            $table->decimal('max_price', 10, 2);
            $table->decimal('min_price', 10, 2);
            $table->decimal('change', 10, 2)->default(0);
            $table->decimal('change_percent', 10, 2)->default(0);
            $table->decimal('previous_closing', 10, 2)->default(0);
            $table->unsignedBigInteger('traded_shares')->default(0);
            $table->unsignedBigInteger('traded_amount')->default(0);
            $table->unsignedBigInteger('total_quantity')->default(0);
            $table->unsignedBigInteger('total_transaction')->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->unsignedInteger('no_of_transactions')->default(0);
            $table->timestamps();

            $table->unique(['stock_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
