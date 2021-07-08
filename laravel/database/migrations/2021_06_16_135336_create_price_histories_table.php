<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePriceHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_id');
            $table->date('date');
            $table->decimal('closing_price', 10, 2);
            $table->decimal('max_price', 10, 2);
            $table->decimal('min_price', 10, 2);
            $table->decimal('change', 10, 2);
            $table->decimal('change_percent', 10, 2);
            $table->decimal('previous_closing', 10, 2);
            $table->bigInteger('traded_shares');
            $table->bigInteger('traded_amount');
            $table->bigInteger('total_quantity');
            $table->bigInteger('total_transaction');
            $table->decimal('total_amount', 16, 2);
            $table->integer('no_of_transactions');
            $table->timestamps();

            $table->foreign('stock_id')->references('id')->on('stocks')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('price_histories');
    }
}
