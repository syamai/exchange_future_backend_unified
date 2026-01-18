<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEnableFeeSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('enable_fee_settings')) {
            Schema::create('enable_fee_settings', function (Blueprint $table) {
                $table->increments('id');

                $table->string('currency');
                $table->string('coin');
                $table->string('email');
                $table->enum('enable_fee', ['enable', 'disable'])->default('enable');

                $table->timestamps();
                $table->index(['currency', 'coin']);
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
        Schema::dropIfExists('enable_fee_settings');
    }
}
