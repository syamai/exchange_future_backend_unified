<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserOrderbooksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_orderbooks', function (Blueprint $table) {
            $table->integer('user_id')->unsigned();
            $table->string('trade_type', 20); // array('buy', 'sell'));
            $table->string('currency', 20);
            $table->string('coin', 20);
            $table->decimal('quantity', 30, 10)->default(0);
            $table->integer('count')->default(0);
            $table->decimal('price', 30, 10);
            $table->decimal('ticker', 30, 10);
            $table->bigInteger('updated_at');

            $table->index('count');
            $table->index('updated_at');
            $table->primary(['user_id', 'trade_type', 'currency', 'coin', 'price', 'ticker'], 'user_row');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_orderbooks');
    }
}
