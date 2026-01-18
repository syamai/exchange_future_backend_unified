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
        Schema::create('future_liquidation_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index();
            $table->string('coin', 20);
            $table->string('symbol')->nullable();
            $table->decimal('rate_with_usdt', 30, 10)->default(1);
            $table->decimal('amount', 30, 10)->nullable();
            $table->bigInteger('executed_time');
            $table->timestamps();

            $table->index(['user_id', 'executed_time']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('future_liquidation_messages');
    }
};
