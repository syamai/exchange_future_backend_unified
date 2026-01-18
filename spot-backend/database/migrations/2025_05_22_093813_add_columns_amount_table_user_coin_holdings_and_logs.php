<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_coin_holdings_logs', function (Blueprint $table) {
            $table->string('order_type')->after('trade_type');
            $table->decimal('fee', 32, 12)->after('order_type')->default(0);
            $table->decimal('executed_price', 32, 12)->after('fee')->default(0);
            $table->decimal('base_price', 32, 12)->after('executed_price')->nullable();
            $table->string('stop_condition')->after('base_price')->nullable();
            $table->decimal('reverse_price', 32, 12)->after('stop_condition')->nullable();
            $table->string('market_type')->after('reverse_price')->default(0);
        });
        Schema::table('user_coin_holdings', function (Blueprint $table) {
            $table->decimal('total_fees_paid', 32, 12)->after('net_quantity')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_coin_holdings_logs', function (Blueprint $table) {
            $table->dropColumn(['order_type', 'fee', 'executed_price', 'base_price', 'stop_condition', 'reverse_price', 'market_type']);
        });
        Schema::table('user_coin_holdings', function (Blueprint $table) {
            $table->dropColumn(['total_fees_paid']);
        });
    }
};
