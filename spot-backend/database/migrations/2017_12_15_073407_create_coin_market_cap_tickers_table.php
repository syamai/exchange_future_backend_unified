<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoinMarketCapTickersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coin_market_cap_tickers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('symbol')->nullable();
            $table->unsignedInteger('rank')->default(0)->nullable();
            $table->double('price_usd')->default(0)->nullable();
            $table->double('price_btc')->default(0)->nullable();
            $table->double('24h_volume_usd')->default(0)->nullable();
            $table->double('market_cap_usd')->default(0)->nullable();
            $table->double('available_supply')->default(0)->nullable();
            $table->double('total_supply')->default(0)->nullable();
            $table->double('max_supply')->default(0)->nullable();
            $table->float('percent_change_1h')->default(0)->nullable();
            $table->float('percent_change_24h')->default(0)->nullable();
            $table->float('percent_change_7d')->default(0)->nullable();
            $table->timestamp('last_updated')->nullable();

            // Convert price follow 'settings'.'currency_country'
            $table->double('price_currency')->default(0)->nullable();
            $table->double('24h_volume_currency')->default(0)->nullable();
            $table->double('market_cap_currency')->default(0)->nullable();

            $table->timestamps();

            $table->index('name');
            $table->index('symbol');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('coin_market_cap_tickers');
    }
}
