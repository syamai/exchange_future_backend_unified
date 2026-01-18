<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInstrumentExtraInformations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('instrument_extra_informations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('symbol');
            $table->decimal('impact_bid_price', 30, 10)->nullable();
            $table->decimal('impact_mid_price', 30, 10)->nullable();
            $table->decimal('impact_ask_price', 30, 10)->nullable();
            $table->decimal('fair_basis_rate', 30, 10)->nullable();
            $table->decimal('fair_basis', 30, 10)->nullable();
            $table->decimal('fair_price', 30, 10)->nullable();
            $table->decimal('mark_price', 30, 10)->nullable();
            $table->dateTime('funding_timestamp')->nullable();
            $table->decimal('funding_rate', 30, 10)->nullable();
            $table->decimal('indicative_funding_rate', 30, 10)->nullable();
            $table->decimal('last_price', 30, 10)->nullable();
            $table->decimal('last_price_24h', 30, 10)->nullable();
            $table->decimal('ask_price', 30, 10)->nullable();
            $table->decimal('bid_price', 30, 10)->nullable();
            $table->decimal('mid_price', 30, 10)->nullable();
            $table->date('trade_reported_at')->nullable();
            $table->decimal('max_value_24h', 30, 10)->nullable()->default(0);
            ;
            $table->decimal('min_value_24h', 30, 10)->nullable()->default(0);
            $table->decimal('total_turnover_24h', 30, 10)->nullable()->default(0);
            $table->decimal('total_volume_24h', 30, 10)->nullable()->default(0);
            $table->decimal('total_volume', 30)->nullable()->default(0);
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
        Schema::dropIfExists('instrument_extra_informations');
    }
}
