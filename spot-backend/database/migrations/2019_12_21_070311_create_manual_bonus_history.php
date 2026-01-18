<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateManualBonusHistory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dividend_manual_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('email');
            $table->string('coin');
            $table->string('market');
            $table->decimal('total_trade_volume', 30, 10);
            $table->decimal('bonus_amount', 30, 10);
            $table->string('status');
            $table->string('type');
            $table->string('balance')->nullable();
            $table->string('bonus_currency')->nullable();
            $table->dateTime('filter_from')->nullable();
            $table->dateTime('filter_to')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nulllable();

            $table->index('user_id');
            $table->index('email');
            $table->index('coin');
            $table->index('market');
            $table->index(['filter_from', 'filter_to']);
            $table->index(['coin', 'market']);
        });
        //
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
