<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCircuitBreakerSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('circuit_breaker_settings')) {
            Schema::create('circuit_breaker_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->decimal('range_listen_time', 20, 5)->default(1);   // Default: 1 hour
                $table->decimal('circuit_breaker_percent', 30, 10)->default(0);
                $table->decimal('block_time', 20, 5)->default(1);  // Default: 1 hour
                $table->enum('status', ['disable', 'enable'])->default('enable');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('circuit_breaker_settings');
    }
}
