<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoinSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coin_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('currency', 20);
            $table->string('coin', 20);
            $table->decimal('minimum_quantity', 30, 10);
            $table->decimal('precision', 30, 10)->default(0.00000001);
            $table->decimal('minimum_amount', 30, 10)->default(0.001);
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
        Schema::dropIfExists('coin_settings');
    }
}
