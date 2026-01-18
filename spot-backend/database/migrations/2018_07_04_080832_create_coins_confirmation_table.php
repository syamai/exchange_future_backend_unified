<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoinsConfirmationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coins_confirmation', function (Blueprint $table) {
            $table->increments('id');
            $table->string('coin', 20);
            $table->integer('confirmation');
            $table->tinyInteger('is_withdraw')->default(0);
            $table->tinyInteger('is_deposit')->default(0);
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
        Schema::dropIfExists('coins_confirmation');
    }
}
