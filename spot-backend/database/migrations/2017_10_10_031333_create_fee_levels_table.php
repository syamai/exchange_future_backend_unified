<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFeeLevelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fee_levels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('level');
            $table->decimal('amount', 30, 10);
            $table->decimal('mgc_amount', 30, 10);
            $table->decimal('fee_taker', 30, 10);
            $table->decimal('fee_maker', 30, 10);
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
        Schema::dropIfExists('fee_levels');
    }
}
