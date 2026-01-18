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
        if (Schema::hasTable('gains_statistics_overview')) {
            Schema::table('gains_statistics_overview', function (Blueprint $table) {
                $table->dropPrimary('PRIMARY');
            });
            Schema::table('gains_statistics_overview', function (Blueprint $table) {
                $table->id()->first();
            });
            Schema::table('gains_statistics_overview', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->index()->change();
            });
        }
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('gains_statistics_overview')) {
            Schema::table('gains_statistics_overview', function (Blueprint $table) {
                $table->dropColumn('id');
            });
            Schema::table('gains_statistics_overview', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->primary()->change();
            });
        }
    }
};
