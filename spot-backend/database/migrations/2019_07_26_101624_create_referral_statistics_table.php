<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReferralStatisticsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referral_statistics', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->decimal('ada_balances', 30, 10)->default(0);
            $table->decimal('amal_balances', 30, 10)->default(0);
            $table->decimal('bch_balances', 30, 10)->default(0);
            $table->decimal('btc_balances', 30, 10)->default(0);
            $table->decimal('eos_balances', 30, 10)->default(0);
            $table->decimal('eth_balances', 30, 10)->default(0);
            $table->decimal('ltc_balances', 30, 10)->default(0);
            $table->decimal('usd_balances', 30, 10)->default(0);
            $table->decimal('usdt_balances', 30, 10)->default(0);
            $table->decimal('xrp_balances', 30, 10)->default(0);
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
        Schema::dropIfExists('referral_statistics');
    }
}
