<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AmalSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('amal_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->decimal('total', 30, 10)->default(0);
            $table->decimal('amount', 30, 10)->default(0);

            $table->decimal('usd_price', 30, 10)->default(0);
            $table->decimal('eth_price', 30, 10)->default(0);
            $table->decimal('btc_price', 30, 10)->default(0);

            $table->decimal('usd_price_presenter', 30, 10)->default(0);
            $table->decimal('eth_price_presenter', 30, 10)->default(0);
            $table->decimal('btc_price_presenter', 30, 10)->default(0);

            $table->decimal('usd_price_presentee', 30, 10)->default(0);
            $table->decimal('eth_price_presentee', 30, 10)->default(0);
            $table->decimal('btc_price_presentee', 30, 10)->default(0);

            $table->decimal('usd_sold_amount', 30, 10)->default(0);
            $table->decimal('eth_sold_amount', 30, 10)->default(0);
            $table->decimal('btc_sold_amount', 30, 10)->default(0);

            $table->decimal('usd_sold_money', 30, 10)->default(0);
            $table->decimal('eth_sold_money', 30, 10)->default(0);
            $table->decimal('btc_sold_money', 30, 10)->default(0);

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
        Schema::dropIfExists('amal_settings');
    }
}
