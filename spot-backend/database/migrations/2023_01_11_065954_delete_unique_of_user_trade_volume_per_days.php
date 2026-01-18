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
        Schema::table('user_trade_volume_per_days', function (Blueprint $table) {
            $table->dropUnique('date_user_unique');
            $table->string('type')->default('spot')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_trade_volume_per_days', function (Blueprint $table) {
            $table->unique(['user_id'], 'date_user_unique');
            $table->integer('type')->change();
        });
    }
};
