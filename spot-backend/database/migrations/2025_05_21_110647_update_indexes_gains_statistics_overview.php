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
        Schema::table('gains_statistics_overview', function (Blueprint $table) {
            $table->dropIndex('gains_statistics_overview_user_id_index');
            $table->unique(['user_id', 'currency'], 'uniq_user_currency');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gains_statistics_overview', function (Blueprint $table) {
            $table->dropIndex('uniq_user_currency');
        });
    }
};
