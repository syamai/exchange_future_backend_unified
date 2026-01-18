<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBitmexMappingOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bitmex_mapping_order', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('trade_id')->unique();
            $table->unsignedInteger('buy_order_id');
            $table->unsignedInteger('sell_order_id');
            $table->string('user_email')->nullable();
            $table->string('bot_email')->nullable();
            $table->string('symbol', 20);
            $table->decimal('price', 30, 10);
            $table->string('user_order_side', 20);
            $table->decimal('user_order_qty', 30, 10);

            $table->string('bitmex_order_id')->nullable();
            $table->unsignedInteger('bitmex_account_id')->nullable();
            $table->string('bitmex_account_email')->nullable();
            $table->string('bitmex_order_side', 20)->nullable();
            $table->decimal('bitmex_matched_order_qty', 30, 10)->default(0);
            $table->decimal('bitmex_remaining_order_qty', 30, 10)->default(0);
            $table->enum('status', ['pending', 'retrying', 'done', 'failed'])->default('pending');
            $table->unsignedInteger('retry')->default(0);
            $table->unsignedInteger('max_retry')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('user_email');
            $table->index('bot_email');
            $table->index('bitmex_account_id');
            $table->index('bitmex_account_email');
            $table->index(['symbol', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bitmex_mapping_order');
    }
}
