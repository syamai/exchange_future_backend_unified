<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('original_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->string('email')->nullable();
            $table->string('trade_type', 20); //array('buy', 'sell'));
            $table->string('currency', 20);
            $table->string('coin', 20);
            $table->string('type', 20); // array('limit', 'market', 'stop_limit', 'stop_market'));
            $table->unsignedTinyInteger('ioc')->nullable();
            $table->decimal('quantity', 30, 10);
            $table->decimal('price', 30, 10)->nullable();
            $table->decimal('executed_quantity', 30, 10)->default(0);
            $table->decimal('executed_price', 30, 10)->default(0);
            $table->decimal('base_price', 30, 10)->nullable();
            $table->string('stop_condition', 20)->nullable(); // array('ge', 'le') greater than or equal, less than or equal
            $table->decimal('fee', 30, 10)->nullable();
            $table->string('status', 20); // array('pending', 'executed', 'canceled', 'executing', 'stopping', 'removed'));
            $table->bigInteger('created_at');
            $table->bigInteger('updated_at');
            $table->decimal('reverse_price', 30, 10)->nullable();

            $table->index('original_id');
            $table->index(['status', 'currency', 'coin', 'stop_condition', 'base_price'], 'active_stop_orders');
            $table->index(['user_id', 'currency', 'status', 'original_id'], 'order_list');
            $table->index(['status', 'trade_type', 'currency', 'coin', 'price', 'updated_at'], 'load_sell_orders_to_queue');
            $table->index(['status', 'trade_type', 'currency', 'coin', 'reverse_price', 'updated_at'], 'load_buy_orders_to_queue');
            $table->index(['status', 'trade_type', 'currency', 'coin', 'updated_at'], 'load_market_orders_to_queue');
            $table->index(['user_id', 'trade_type', 'currency', 'coin', 'type', 'status'], 'cancel_orders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
