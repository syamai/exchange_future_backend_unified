<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_coin_holdings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('coin');
            $table->decimal('total_buy', 32, 12)->default(0);
            $table->decimal('total_sell', 32, 12)->default(0);
            $table->decimal('net_quantity', 32, 12)->virtualAs('total_buy - total_sell');
            $table->unsignedBigInteger('last_updated_at')->nullable();
            
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();

            $table->unique(['user_id', 'coin']);
        });

        Schema::create('user_coin_holdings_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id');
            $table->string('coin');
            $table->enum('trade_type', ['buy', 'sell']);
            $table->decimal('executed_quantity_delta', 32, 12);
            $table->decimal('total_executed_after', 32, 12);
            $table->unsignedBigInteger('logged_at');

            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();
            
            $table->unique(['order_id', 'total_executed_after']); // tránh log trùng
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_coin_holdings_logs');
        Schema::dropIfExists('user_coin_holdings');
    }
};
