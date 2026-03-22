<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('status', 32)->default('queued');
            $table->string('batch_id')->nullable();
            $table->dateTimeTz('start');
            $table->dateTimeTz('end')->nullable();
            $table->unsignedInteger('total_time')->nullable()->comment('In seconds');
            $table->unsignedInteger('total_synced')->default(0);
            $table->unsignedInteger('total_stocks')->default(0);
            $table->unsignedInteger('processed_stocks')->default(0);
            $table->text('error_summary')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
