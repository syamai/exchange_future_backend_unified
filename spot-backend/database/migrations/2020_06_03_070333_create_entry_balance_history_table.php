<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEntryBalanceHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('entry_balance_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('buy_order_id')->nullable();
            $table->unsignedInteger('sell_order_id')->nullable();
            $table->unsignedInteger('account_id')->nullable();
            $table->unsignedInteger('contest_id')->nullable();
            $table->string('symbol', 191)->nullable();
            $table->integer('quantity')->nullable();
            $table->decimal('balance', 30, 15)->default(0);
            $table->decimal('realised_pnl', 30, 15)->default(0);
            $table->decimal('close_margin', 30, 15)->default(0);
            $table->decimal('available_balance', 30, 15)->default(0);
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
        Schema::dropIfExists('entry_balance_history');
    }
}
