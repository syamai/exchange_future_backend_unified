<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeMultiReferrerDetail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('referrer_multi_level_details', function (Blueprint $table) {
            $table->bigInteger('number_of_referrer_lv_1')->default(0);
            $table->bigInteger('number_of_referrer_lv_2')->default(0);
            $table->bigInteger('number_of_referrer_lv_3')->default(0);
            $table->bigInteger('number_of_referrer_lv_4')->default(0);
            $table->bigInteger('number_of_referrer_lv_5')->default(0);
        });
        if (Schema::hasColumn('referrer_multi_level_details', 'number_of_referrer')) {
            Schema::table('referrer_multi_level_details', function (Blueprint $table) {
                $table->dropColumn('number_of_referrer');
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
        Schema::table('referrer_multi_level_details', function (Blueprint $table) {
            //
        });
    }
}
