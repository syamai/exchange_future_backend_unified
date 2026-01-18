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
        Schema::create('ip_stack_logs', function (Blueprint $table) {
            $table->id();
            $table->string("ip", 32)->unique();
            $table->string("region_name", 64)->nullable();
            $table->string("region_code", 32)->nullable();
            $table->string("country_name", 64)->nullable();
            $table->string("country_code", 2)->nullable();
            $table->string("latitude", 64)->nullable();
            $table->string("longitude", 64)->nullable();
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
        Schema::dropIfExists('ip_stack_logs');
    }
};
