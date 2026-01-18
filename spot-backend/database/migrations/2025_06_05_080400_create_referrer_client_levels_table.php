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
        Schema::create('referrer_client_levels', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->unique();
            $table->unsignedInteger('trade_min');
            $table->decimal('volume', 20, 3)->default(0);
            $table->decimal('rate', 5, 2)->default(0);
            $table->string('label');
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
        Schema::dropIfExists('referrer_client_levels');
    }
};
