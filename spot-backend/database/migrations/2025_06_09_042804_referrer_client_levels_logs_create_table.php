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
        Schema::create('referrer_client_levels_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level');
            $table->unsignedInteger('trade_min');
            $table->unsignedInteger('trade_min_before');
            $table->decimal('volume', 20, 3)->default(0);
            $table->decimal('volume_before', 20, 3)->default(0);
            $table->decimal('rate', 5, 2)->default(0);
            $table->decimal('rate_before', 5, 2)->default(0);
            $table->string('label');
            $table->string('label_before');
            $table->string('actor')->nullable();
            $table->unsignedBigInteger('logged_at')->nullable();

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
        Schema::dropIfExists('referrer_client_levels_logs');
    }
};
