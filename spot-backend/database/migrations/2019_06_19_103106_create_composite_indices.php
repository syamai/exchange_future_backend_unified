<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCompositeIndices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('composite_indices', function (Blueprint $table) {
            $table->increments('id');
            $table->string('symbol');
            $table->string('index_symbol');
            $table->string('reference');
            $table->decimal('weight', 30, 10)->nullable();
            $table->decimal('value', 30, 10);
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
        Schema::dropIfExists('composite_indice');
    }
}
