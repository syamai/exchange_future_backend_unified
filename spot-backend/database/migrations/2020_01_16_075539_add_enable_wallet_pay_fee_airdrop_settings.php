<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEnableWalletPayFeeAirdropSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('airdrop_settings', function (Blueprint $table) {
            //
            $table->boolean("enable_wallet_pay_fee")->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('airdrop_settings', function (Blueprint $table) {
            //
        });
    }
}
