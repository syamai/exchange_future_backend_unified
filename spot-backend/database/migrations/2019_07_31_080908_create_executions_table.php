<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExecutionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('executions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->string('instrument_symbol');
            $table->string('exec_type');
            $table->string('side');
            $table->integer('exec_quantity');
            $table->decimal('exec_price', 30, 10);
            $table->decimal('exec_value', 30, 10);
            $table->string('order_type')->nullable()->default(null);
            $table->decimal('order_price', 30, 10);
            $table->integer('order_quantity');
            $table->unsignedInteger('order_id')->nullable()->default(null);
            $table->string('note')->nullable()->default(null);
            $table->decimal('fee_rate', 30, 10);
            $table->decimal('fee_value', 30, 10);
            $table->unsignedInteger('trade_id')->nullable()->default(null);
            $table->integer('leaves_quantity')->default(0);
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
        Schema::dropIfExists('executions');
    }
}
