<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance indexes for matching engine optimization (FR-DB-001)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Index for OrderBook loading: used in loadFromDatabase()
            // Query pattern: WHERE currency = ? AND coin = ? AND status IN (?)
            $table->index(['currency', 'coin', 'status'], 'idx_orders_currency_coin_status');

            // Index for status-based queries with time ordering
            // Query pattern: WHERE status = ? ORDER BY updated_at
            $table->index(['status', 'updated_at'], 'idx_orders_status_updated');

            // Index for user-specific trading pair queries
            // Query pattern: WHERE user_id = ? AND currency = ? AND coin = ?
            $table->index(['user_id', 'currency', 'coin'], 'idx_orders_user_currency_coin');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_currency_coin_status');
            $table->dropIndex('idx_orders_status_updated');
            $table->dropIndex('idx_orders_user_currency_coin');
        });
    }
};
