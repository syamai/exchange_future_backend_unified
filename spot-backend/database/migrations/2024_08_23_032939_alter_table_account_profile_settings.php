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
        Schema::table('account_profile_settings', function (Blueprint $table) {
            // Check if all columns exist before making changes
            if (Schema::hasColumns('account_profile_settings', [
                'spot_market_marker_allow', 'future_trade_allow', 'future_trading_fee_allow',
                'future_market_marker_allow', 'future_coin_pair_trade'
            ])) {
                $table->unsignedInteger('spot_market_marker_allow')->default(0)->change();

                // Drop columns after confirming their existence
                $table->dropColumn([
                    'future_trade_allow', 'future_trading_fee_allow',
                    'future_market_marker_allow', 'future_coin_pair_trade'
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('account_profile_settings', function (Blueprint $table) {
            // Revert the column addition
            $table->dropColumn('spot_market_marker_allow');

            // Add back the dropped columns
            $table->unsignedInteger('future_trade_allow')->default(0);
            $table->unsignedInteger('future_trading_fee_allow')->default(0);
            $table->unsignedInteger('future_market_marker_allow')->default(0);
            $table->string('future_coin_pair_trade')->nullable();
        });
    }
};
