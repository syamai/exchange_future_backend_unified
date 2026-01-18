<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAutoDividendSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dividend_auto_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->tinyInteger('enable')->nullable();
            $table->string('market')->nullable();
            $table->string('coin')->nullable();
            $table->date('time_from')->nullable();
            $table->date('time_to')->nullable();
            $table->decimal('max_bonus', 30, 10)->nullable();
            $table->decimal('payout_amount', 30, 10)->nullable();
            $table->decimal('lot', 30, 10)->nullable();
            $table->string('payout_coin')->nullable();
            $table->string('payfor')->nullable();
            $table->string('setting_for')->nullable();
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
        Schema::dropIfExists('dividend_auto_settings');
    }
}
