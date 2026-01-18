<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderbooksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orderbooks', function (Blueprint $table) {
            $table->string('trade_type', 20); // array('buy', 'sell'));
            $table->string('currency', 20);
            $table->string('coin', 20);
            $table->decimal('quantity', 30, 10)->default(0);
            $table->integer('count')->default(0);
            $table->decimal('price', 30, 10);
            $table->decimal('ticker', 30, 10);
            $table->bigInteger('updated_at');

            $table->primary(['trade_type', 'currency', 'coin', 'price', 'ticker']);
            $table->index('count');
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orderbooks');
    }
}
