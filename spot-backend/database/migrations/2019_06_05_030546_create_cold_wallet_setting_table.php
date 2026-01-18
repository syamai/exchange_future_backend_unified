<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateColdWalletSettingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cold_wallet_setting', function (Blueprint $table) {
            $table->increments('id');
            $table->string('coin');
            $table->string('address');
            $table->string('sub_address')->nullable();
            $table->decimal('min_balance', 30, 10);
            $table->decimal('max_balance', 30, 10);
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
        Schema::dropIfExists('cold_wallet_setting');
    }
}
