<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeUserWithdrawalAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_withdrawal_addresses', function (Blueprint $table) {
            $table->dropUnique('user_withdrawal_addresses_user_id_coin_wallet_address_unique');
            $table->unique(['user_id', 'coin', 'wallet_address', 'wallet_sub_address'], 'user_id_coin_wallet');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
