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
       Schema::table('gains_statistics_overview', function (Blueprint $table) {
            $table->decimal('total_buy_value', 32, 12)->after('total_buy')->default(0);  // USDT value
            $table->decimal('total_sell_value', 32, 12)->after('total_sell')->default(0); // USDT value
       });
       Schema::table('user_coin_holdings', function (Blueprint $table) {
            $table->decimal('total_buy_value', 32, 12)->after('total_buy')->default(0);  // USDT value
            $table->decimal('total_sell_value', 32, 12)->after('total_sell')->default(0); // USDT value
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gains_statistics_overview', function (Blueprint $table) {
            $table->dropColumn('total_buy_value');
            $table->dropColumn('total_sell_value');
        });
        Schema::table('user_coin_holdings', function (Blueprint $table) {
            $table->dropColumn('total_buy_value');
            $table->dropColumn('total_sell_value');
        });
    }
};
