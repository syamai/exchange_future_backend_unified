<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddGetOrderbookIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orderbooks', function (Blueprint $table) {
            $table->index(['trade_type', 'currency', 'coin', 'price', 'ticker', 'quantity'], 'get_orderbook');
            $table->dropIndex('orderbooks_count_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
