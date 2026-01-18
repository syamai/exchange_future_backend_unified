<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBitmexAccountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bitmex_account', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->unique();
            $table->string('email')->unique();
            $table->string('key_name');
            $table->string('key_id');
            $table->string('key_secret');
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
        Schema::dropIfExists('bitmex_account');
    }
}
