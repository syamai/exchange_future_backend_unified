<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBonusBalance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('airdrop_amal_accounts', function (Blueprint $table) {
            $table->decimal('balance_bonus', 30, 10)->nullable()->default('0.0000000000');
            $table->decimal('available_balance_bonus', 30, 10)->nullable()->default('0.0000000000');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('airdrop_amal_accounts', function (Blueprint $table) {
            //
        });
    }
}
