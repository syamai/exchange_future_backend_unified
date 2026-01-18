<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('email');
            $table->string('currency', 20);
            $table->decimal('credit', 30, 10)->default(0); //substract
            $table->decimal('debit', 30, 10)->default(0); //add
            $table->decimal('ending_balance', 30, 10)->default(0);
            $table->decimal('commission_btc', 30, 10)->nullable();
            $table->string('type', 20);
            $table->unsignedInteger('transaction_id');
            $table->string('friend_email')->nullable();
            $table->bigInteger('created_at');

            $table->index(['user_id', 'currency', 'created_at']);
            $table->index('email');
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_transactions');
    }
}
