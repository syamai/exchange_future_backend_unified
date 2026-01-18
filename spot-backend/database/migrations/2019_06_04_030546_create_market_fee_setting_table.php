<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMarketFeeSettingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('market_fee_setting', function (Blueprint $table) {
            $table->increments('id');
            $table->string('currency');
            $table->string('coin');
            $table->decimal('fee_taker', 30, 10);
            $table->decimal('fee_maker', 30, 10);
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
        Schema::dropIfExists('market_fee_setting');
    }
}
