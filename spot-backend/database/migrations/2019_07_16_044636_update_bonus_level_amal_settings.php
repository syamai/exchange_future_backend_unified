<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateBonusLevelAmalSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('amal_settings', function (Blueprint $table) {
            $table->decimal('amal_bonus_1', 30, 10)->nullable()->default(null);
            $table->decimal('percent_bonus_1', 30, 10)->nullable()->default(null);
            $table->decimal('amal_bonus_2', 30, 10)->nullable()->default(null);
            $table->decimal('percent_bonus_2', 30, 10)->nullable()->default(null);
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
    }
}
