<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTradingLimitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trading_limits', function (Blueprint $table) {
            $table->increments('id');
            $table->string('coin', 20);
            $table->string('currency', 20);
            $table->decimal('sell_limit', 30, 10);
            $table->decimal('buy_limit', 30, 10);
            $table->unsignedInteger('days');
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
        Schema::dropIfExists('trading_limits');
    }
}
