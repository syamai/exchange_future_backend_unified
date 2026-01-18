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
        Schema::create('user_coin_pendings_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('coin');
            $table->string('currency');
            $table->enum('trade_type', ['buy', 'sell']);
            $table->string('order_type')->nullable();
            $table->decimal('fee', 32, 12)->default(0);
            $table->decimal('executed_price', 32, 12)->default(0);
            $table->decimal('base_price', 32, 12)->nullable();
            $table->decimal('pending_quantity', 32, 12)->default(0);
            $table->decimal('pending_value', 32, 12)->default(0);
            $table->string('stop_condition')->nullable();
            $table->decimal('reverse_price', 32, 12)->nullable();
            $table->string('market_type')->default('0');
            $table->unsignedBigInteger('logged_at'); // timestamp ms

            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();

            $table->unique(['order_id', 'logged_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('table_user_coin_pendings_logs');
    }
};
