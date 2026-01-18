<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SiteSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('app_name')->nullable();
            $table->string('short_name')->nullable();
            $table->string('site_email')->nullable();
            $table->string('site_phone_number')->nullable();
            $table->string('language')->nullable();
            $table->string('copyright')->nullable();
            $table->text('social')->nullable();
            $table->string('ios_app_link')->nullable();
            $table->string('android_app_link')->nullable();
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
        //
    }
}
