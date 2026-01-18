<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDividendCashbackHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dividend_cashback_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cashback_id')->unique();
            $table->unsignedInteger('user_id');
            $table->string('email');
            $table->decimal('amount', 30, 10);
            $table->string('status');
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
        Schema::dropIfExists('dividend_cashback_histories');
    }
}
