<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIndexSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('indices_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('symbol')->unique();
            $table->string('root_symbol');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('type', ['ami', 'constance', 'frequency', 'premium'])->default('constance');
            $table->decimal('precision', 8, 0);
            $table->decimal('value', 30, 10);
            $table->decimal('constance_value', 30, 10)->nullable();
            $table->decimal('previous_value', 30, 10)->nullable();
            $table->decimal('previous_24h_value', 30, 10)->nullable();
            $table->boolean('is_index_price')->nullable();
            $table->string('reference_symbol')->nullable();
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
        Schema::dropIfExists('indices_settings');
    }
}
