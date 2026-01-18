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
        Schema::table('network_coins', function (Blueprint $table) {
            $table->unsignedInteger('coin_id')->after('network_id');
            $table->unique(['network_id', 'coin_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('network_coins', function (Blueprint $table) {
            $table->dropUnique(['network_id', 'coin_id']);
            $table->dropColumn(['coin_id']);
        });
    }
};
