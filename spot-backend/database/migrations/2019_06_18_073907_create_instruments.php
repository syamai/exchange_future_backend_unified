<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInstruments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('symbol')->unique();
            $table->string('root_symbol');
            $table->string('state');
            $table->unsignedInteger('type');
            $table->dateTime('expiry')->nullable()->default(null);
            $table->string('base_underlying')->nullable()->default(null);
            $table->string('quote_currency')->nullable()->default(null);
            $table->string('underlying_symbol')->nullable()->default(null);
            $table->string('settle_currency')->nullable()->default(null);
            $table->decimal('init_margin', 30, 10)->default(0);
            $table->decimal('maint_margin', 30, 10)->default(0);
            $table->boolean('deleverageable')->default(1); // 0-Undeleverageabled, 1-Deleverageabled
            $table->decimal('maker_fee', 30, 10)->default(0);
            $table->decimal('taker_fee', 30, 10)->default(0);
            $table->decimal('settlement_fee', 30, 10)->default(0);
            $table->boolean('has_liquidity')->default(1); // 0-Has not liquidity, 1-Has liquidity
            $table->string('reference_index')->nullable()->default(null);
            $table->string('settlement_index')->nullable()->default(null);
            $table->string('funding_base_index')->nullable()->default("");
            $table->string('funding_quote_index')->nullable()->default("");
            $table->string('funding_premium_index')->nullable()->default("");
            $table->tinyInteger('funding_interval')->nullable()->default(null);
            $table->decimal('tick_size', 30, 10)->default(0);
            $table->decimal('max_price', 30, 10)->default(0);
            $table->integer('max_order_qty')->default(0);
            $table->decimal('multiplier', 30, 10)->default(0);
            $table->decimal('option_strike_price', 30, 10)->nullable()->default(null);
            $table->decimal('option_ko_price', 30, 10)->nullable()->default(null);
            $table->decimal('risk_limit', 20, 8)->nullable()->default(null);
            $table->decimal('risk_step', 20, 8)->nullable()->default(null);
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
        Schema::dropIfExists('instruments');
    }
}
