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
        Schema::create('user_asset_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('currency');
            $table->decimal('total_asset_value', 32, 12)->default(0);
            $table->decimal('total_buy', 32, 12)->default(0);
            $table->decimal('total_buy_value', 32, 12)->default(0);  // USDT value
            $table->decimal('total_sell', 32, 12)->default(0);
            $table->decimal('total_sell_value', 32, 12)->default(0); // USDT value
            $table->unsignedBigInteger('snapshotted_at')->index();
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();
            
            $table->index(['user_id', 'currency', 'snapshotted_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_asset_snapshots');
    }
};
