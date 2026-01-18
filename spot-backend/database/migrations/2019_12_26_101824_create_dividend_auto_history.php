<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDividendAutoHistory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dividend_auto_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('email');
            $table->string('currency')->nullable();
            $table->string('market')->nullable();
            $table->integer('transaction_id');
            $table->decimal('bonus_amount', 30, 10);
            $table->string('bonus_currency')->nullable();
            $table->string('bonus_wallet');
            $table->string('type')->nullable();
            $table->date('bonus_date');
            $table->string('status');
            $table->timestamps();
            $table->index('user_id');
            $table->index('email');
            $table->index('bonus_date');
            $table->index(['currency', 'market']);
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
