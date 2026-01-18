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
        Schema::create('networks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('symbol', 20)->nullable();
            $table->string('name')->nullable();
            $table->string('currency', 20)->nullable();
            $table->string('network_code', 20)->nullable();
            $table->integer('chain_id')->nullable();
            $table->boolean('network_deposit_enable')->default(false);
            $table->boolean('network_withdraw_enable')->default(false);
            $table->integer('deposit_confirmation')->nullable();
            $table->string('explorer_url')->nullable();
            $table->boolean('enable')->default(false);
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
        Schema::dropIfExists('networks');
    }
};
