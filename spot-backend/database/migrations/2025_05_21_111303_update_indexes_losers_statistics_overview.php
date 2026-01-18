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
        Schema::table('losers_statistics_overview', function (Blueprint $table) {
            $table->dropUnique('losers_statistics_overview_user_id_unique');
            $table->unique(['user_id', 'currency'], 'uniq_user_currency_losers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('losers_statistics_overview', function (Blueprint $table) {
            $table->dropUnique('uniq_user_currency_losers');
            $table->unique('user_id', 'losers_statistics_overview_user_id_unique');
        });
    }
};
