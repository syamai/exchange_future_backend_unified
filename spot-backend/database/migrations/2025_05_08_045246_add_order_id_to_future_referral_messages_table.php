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
        Schema::table('future_referral_messages', function (Blueprint $table) {
			$table->bigInteger('buy_order_id')->nullable();
			$table->bigInteger('sell_order_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('future_referral_messages', function (Blueprint $table) {
			$table->dropColumn(['buy_order_id', 'sell_order_id']);
        });
    }
};
