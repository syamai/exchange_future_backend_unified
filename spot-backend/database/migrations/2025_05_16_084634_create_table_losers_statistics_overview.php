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
        Schema::create('losers_statistics_overview', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('uid')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('register_date')->nullable();

            $table->string('currency');
            $table->decimal('total_buy', 32, 12)->default(0);
            $table->decimal('total_buy_value', 32, 12)->default(0);  // USDT value
            $table->decimal('total_sell', 32, 12)->default(0);
            $table->decimal('total_sell_value', 32, 12)->default(0); // USDT value
            $table->decimal('peak_asset_value', 32, 12)->default(0);
            $table->decimal('current_asset_value', 32, 12)->default(0);
            $table->decimal('asset_reduction_amount', 32, 12)->default(0);
            $table->decimal('asset_reduction_percent', 8, 4)->default(0); // ví dụ: 80.1234%
            $table->integer('is_loser')->default(0);
            $table->unsignedBigInteger('calculated_at'); // milliseconds timestamp
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('losers_statistics_overview');
    }
};
