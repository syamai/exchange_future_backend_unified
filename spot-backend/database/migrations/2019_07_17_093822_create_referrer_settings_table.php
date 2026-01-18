<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReferrerSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referrer_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('enable')->default(true);
            $table->unsignedInteger('number_of_levels')->nullable()->default(null);
            $table->unsignedInteger('refund_rate')->nullable()->default(null);
            $table->unsignedInteger('refund_percent_at_level_1')->nullable()->default(null);
            $table->unsignedInteger('refund_percent_at_level_2')->nullable()->default(null);
            $table->unsignedInteger('refund_percent_at_level_3')->nullable()->default(null);
            $table->unsignedInteger('refund_percent_at_level_4')->nullable()->default(null);
            $table->unsignedInteger('refund_percent_at_level_5')->nullable()->default(null);
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
        Schema::dropIfExists('referrer_settings');
    }
}
