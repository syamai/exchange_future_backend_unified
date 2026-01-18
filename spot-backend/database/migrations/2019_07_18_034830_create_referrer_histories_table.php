<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReferrerHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referrer_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('email');
            $table->decimal('amount', 30, 10)->default(0);
            $table->string('coin');
            $table->decimal('commission_rate', 5, 2);
            $table->unsignedInteger('order_transaction_id');
            $table->unsignedInteger('transaction_owner');
            $table->timestamps();

            $table->index(['user_id', 'coin']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('referrer_histories');
    }
}
