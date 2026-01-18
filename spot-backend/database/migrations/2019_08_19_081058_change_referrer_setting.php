<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeReferrerSetting extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('referrer_settings', function (Blueprint $table) {
            $table->decimal('refund_percent_at_level_1', 13, 10)->nullable()->default(null)->change();
            ;
            $table->decimal('refund_percent_at_level_2', 13, 10)->nullable()->default(null)->change();
            ;
            $table->decimal('refund_percent_at_level_3', 13, 10)->nullable()->default(null)->change();
            ;
            $table->decimal('refund_percent_at_level_4', 13, 10)->nullable()->default(null)->change();
            ;
            $table->decimal('refund_percent_at_level_5', 13, 10)->nullable()->default(null)->change();
            ;
            $table->decimal('refund_rate', 13, 10)->nullable()->default(null)->change();
            $table->decimal('refund_percent_in_next_program_lv_1', 13, 10)->nullable()->default(null);
            $table->decimal('refund_percent_in_next_program_lv_2', 13, 10)->nullable()->default(null);
            $table->decimal('refund_percent_in_next_program_lv_3', 13, 10)->nullable()->default(null);
            $table->decimal('refund_percent_in_next_program_lv_4', 13, 10)->nullable()->default(null);
            $table->decimal('refund_percent_in_next_program_lv_5', 13, 10)->nullable()->default(null);
            $table->unsignedInteger('number_people_in_next_program')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('referrer_settings', function (Blueprint $table) {
            //
        });
    }
}
