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
        Schema::create('user_asset_commission_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('coin');
            $table->decimal('commission_rate', 10, 4)->nullable();
            $table->decimal('total_spot_commission_amount', 30, 10)->nullable();
            $table->decimal('total_future_commission_amount', 30, 10)->nullable();
            $table->decimal('total_spot_commission_usdt_value', 30, 10)->nullable();
            $table->decimal('total_future_commission_usdt_value', 30, 10)->nullable();
            $table->unsignedBigInteger('reported_at')->nullable();
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();

            $table->unique(['user_id', 'coin'], 'uniq_user_coin');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_asset_commission_rates');
    }
};
