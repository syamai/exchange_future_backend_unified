<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLastOrderTransactionIdField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('circuit_breaker_coin_pair_settings', 'last_order_transaction_id')) {
            Schema::table('circuit_breaker_coin_pair_settings', function (Blueprint $table) {
                $table->bigInteger('last_order_transaction_id')->nullable();
            });
        }

        if (!Schema::hasColumn('circuit_breaker_coin_pair_settings', 'block_trading')) {
            Schema::table('circuit_breaker_coin_pair_settings', function (Blueprint $table) {
                $table->boolean('block_trading')->default(false);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('circuit_breaker_coin_pair_settings', 'last_order_transaction_id')) {
            Schema::table('circuit_breaker_coin_pair_settings', function (Blueprint $table) {
                $table->dropColumn('last_order_transaction_id');
            });
        }

        if (Schema::hasColumn('circuit_breaker_coin_pair_settings', 'block_trading')) {
            Schema::table('circuit_breaker_coin_pair_settings', function (Blueprint $table) {
                $table->dropColumn('block_trading');
            });
        }
    }
}
