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
        Schema::create('history_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('voucher_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->decimal('reward', 30, 10)->default(0);
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
        Schema::dropIfExists('history_rewards');
    }
};
