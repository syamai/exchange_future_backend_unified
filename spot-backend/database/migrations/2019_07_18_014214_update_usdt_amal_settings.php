<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateUsdtAmalSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('amal_settings', function (Blueprint $table) {
            $table->decimal('usdt_price', 30, 10)->default(0);
            $table->decimal('usdt_price_presenter', 30, 10)->default(0);
            $table->decimal('usdt_price_presentee', 30, 10)->default(0);
            $table->decimal('usdt_sold_amount', 30, 10)->default(0);
            $table->decimal('usdt_sold_money', 30, 10)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
