<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTradingVolumeRanking extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('trading_volume_ranking', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('email');
            $table->decimal('volume', 30, 10)->default(0);
            $table->string('coin');
            $table->string('market')->nullable();
            $table->string('type');
            $table->decimal('btc_volume', 30, 10)->default(0);
            $table->decimal('self_trading', 30, 10)->default(0);
            $table->decimal('self_trading_btc_volume', 30, 10)->default(0);
            $table->decimal('trading_volume', 30, 10)->default(0);

            $table->timestamps();

            $table->index('user_id');
            $table->index('coin');
            $table->index('created_at');
            $table->index('type');
            $table->index(['user_id', 'coin', 'created_at','type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists('trading_volume_ranking');
    }
}
