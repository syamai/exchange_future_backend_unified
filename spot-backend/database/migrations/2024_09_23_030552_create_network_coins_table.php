<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('network_coins', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('network_id');
            $table->string('contract_address')->nullable();
            $table->boolean('network_deposit_enable')->default(false);
            $table->boolean('network_withdraw_enable')->default(false);
            $table->boolean('network_enable')->default(false);
            $table->string('token_explorer_url')->nullable();
            $table->decimal('withdraw_fee', 30, 10)->default(0);
            $table->decimal('min_deposit', 30, 10)->default(0);
            $table->decimal('min_withdraw', 30, 10)->default(0);
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
        Schema::dropIfExists('network_coins');
    }
};
