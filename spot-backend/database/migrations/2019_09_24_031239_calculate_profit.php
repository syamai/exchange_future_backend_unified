<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CalculateProfit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calculate_profit_daily', function (Blueprint $table) {
            $table->increments('id');
            $table->date('date');
            $table->string('coin', 20);
            $table->decimal('receive_fee', 30, 10);
            $table->decimal('referral_fee', 30, 10);
            $table->decimal('net_fee', 30, 10);
            $table->timestamps();

            $table->index('date');
            $table->index('coin');
            $table->index(['date', 'coin']);
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
