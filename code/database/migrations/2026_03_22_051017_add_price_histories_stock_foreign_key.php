<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('price_histories') || ! Schema::hasTable('stocks')) {
            return;
        }

        if ($this->hasStockForeignKey()) {
            return;
        }

        Schema::table('price_histories', function (Blueprint $table) {
            $table->foreign('stock_id')
                ->references('id')
                ->on('stocks')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('price_histories') || ! $this->hasStockForeignKey()) {
            return;
        }

        Schema::table('price_histories', function (Blueprint $table) {
            $table->dropForeign(['stock_id']);
        });
    }

    private function hasStockForeignKey(): bool
    {
        return match (DB::getDriverName()) {
            'mysql' => (bool) DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'price_histories')
                ->where('CONSTRAINT_NAME', 'price_histories_stock_id_foreign')
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->exists(),
            'sqlite' => collect(DB::select("PRAGMA foreign_key_list('price_histories')"))
                ->contains(fn (object $foreignKey): bool => ($foreignKey->table ?? null) === 'stocks'
                    && ($foreignKey->from ?? null) === 'stock_id'),
            default => false,
        };
    }
};
