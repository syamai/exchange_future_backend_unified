<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('buy_order_id');
            $table->unsignedInteger('sell_order_id');
            $table->string('currency', 20);
            $table->string('coin', 20);
            $table->decimal('price', 30, 10);
            $table->decimal('quantity', 30, 10);
            $table->decimal('amount', 30, 10);
            $table->decimal('btc_amount', 30, 10);
            $table->string('status', 20); // array('pending', 'executed', 'canceled', 'executing', 'stopping'));
            $table->date('executed_date')->nullable();
            $table->decimal('buy_fee', 30, 10)->nullable();
            $table->decimal('sell_fee', 30, 10)->nullable();
            $table->string('transaction_type', 20); //array('buy', 'sell'));
            $table->unsignedInteger('buyer_id');
            $table->string('buyer_email');
            $table->unsignedInteger('seller_id');
            $table->string('seller_email');
            $table->bigInteger('created_at');
            $table->index('buy_order_id');
            $table->index('sell_order_id');
            $table->index('created_at');
            $table->index(['buyer_id', 'created_at'], 'buy_volume_index');
            $table->index(['seller_id', 'created_at'], 'sell_volume_index');
            $table->index(['executed_date', 'buy_fee'], 'buy_fee_index');
            $table->index(['executed_date', 'sell_fee'], 'sell_fee_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_transactions');
    }
}
