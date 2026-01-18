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
        Schema::create('market_volumes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('top');
            $table->decimal('quantity', 30, 10)->default(0);
            $table->decimal('lastest_price', 30, 10)->default(0);
            $table->string('changed_percent');
            $table->bigInteger('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('market_volumes');
    }
};
