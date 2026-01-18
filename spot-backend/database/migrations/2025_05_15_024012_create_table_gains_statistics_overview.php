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
        Schema::create('gains_statistics_overview', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('uid')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('register_date')->nullable();

            $table->decimal('total_deposit', 30, 10)->nullable();       // totalDeposit
            $table->decimal('total_buy', 30, 10)->nullable();           // totalBuy
            $table->decimal('total_sell', 30, 10)->nullable();          // totalSell
            $table->decimal('total_asset_value', 38, 18)->nullable();   // totalAssetValue
            $table->decimal('net_gain', 30, 10)->nullable();            // netGain
            $table->decimal('gain_percent', 10, 2)->nullable();         // gainPercent

            $table->unsignedBigInteger('calculated_at')->nullable();             // optional: khi snapshot được tính
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();       // created_at / updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gains_statistics_overview');
    }
};
