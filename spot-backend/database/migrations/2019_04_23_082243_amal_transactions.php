<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AmalTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('amal_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->decimal('amount', 30, 10);
            $table->string('currency');
            $table->decimal('bonus', 30, 10)->default(0);
            $table->decimal('total', 30, 10);
            $table->decimal('price', 30, 10);
            $table->decimal('price_bonus', 30, 10);
            $table->decimal('payment', 30, 10);
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
        Schema::dropIfExists('amal_transactions');
    }
}
