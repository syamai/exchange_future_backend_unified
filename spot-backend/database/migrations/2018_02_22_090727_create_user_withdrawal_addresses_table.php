<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserWithdrawalAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_withdrawal_addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('coin', 20);
            $table->string('wallet_name');
            $table->string('wallet_sub_address')->nullable();
            $table->string('wallet_address');
            $table->boolean('is_whitelist')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'coin', 'wallet_address']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_withdrawal_addresses');
    }
}
