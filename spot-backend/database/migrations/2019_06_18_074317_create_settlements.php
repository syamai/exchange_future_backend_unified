<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSettlements extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->increments('id');
            $table->string('symbol');
            $table->decimal('settled_price', 30, 10);
            $table->decimal('option_strike_price', 30, 10);
            $table->decimal('option_underlying_price', 30, 10);
            $table->decimal('tax_base', 30, 10);
            $table->decimal('tax_rate', 30, 10);
            $table->string('settlement_type')->default('Settlement');
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
        Schema::dropIfExists('settlement');
    }
}
