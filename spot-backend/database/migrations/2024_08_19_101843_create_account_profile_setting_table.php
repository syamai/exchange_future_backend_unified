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
        Schema::create('account_profile_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('spot_trade_allow')->default(1);
            $table->unsignedInteger('spot_trading_fee_allow')->default(1);
            $table->unsignedInteger('spot_market_marker_allow')->default(1);
            $table->unsignedInteger('future_trade_allow')->nullable();
            $table->unsignedInteger('future_trading_fee_allow')->nullable();
            $table->unsignedInteger('future_market_marker_allow')->nullable();
            $table->json('spot_coin_pair_trade')->nullable();
            $table->json('future_coin_pair_trade')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_profile_setting');
    }
};
