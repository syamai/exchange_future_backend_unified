<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMultiReferrerDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referrer_multi_level_details', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->unsignedInteger('referrer_id_lv_1')->nullable()->default(null);
            $table->unsignedInteger('referrer_id_lv_2')->nullable()->default(null);
            $table->unsignedInteger('referrer_id_lv_3')->nullable()->default(null);
            $table->unsignedInteger('referrer_id_lv_4')->nullable()->default(null);
            $table->unsignedInteger('referrer_id_lv_5')->nullable()->default(null);
            $table->unsignedBigInteger('number_of_referrer')->default(0);
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
        Schema::dropIfExists('referrer_multi_level_details');
    }
}
