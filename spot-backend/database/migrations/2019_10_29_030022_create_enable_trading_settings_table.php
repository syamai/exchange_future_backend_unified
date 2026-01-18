<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEnableTradingSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('enable_trading_settings')) {
            Schema::create('enable_trading_settings', function (Blueprint $table) {
                $table->increments('id');

                $table->string('currency');
                $table->string('coin');
                $table->string('email');
                $table->enum('enable_trading', ['enable', 'disable'])->default('enable');

                $table->timestamps();
                $table->index(['coin', 'currency']);
                $table->index(['email', 'coin', 'currency']);
                $table->index(['email']);
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
        if (Schema::hasTable('enable_trading_settings')) {
            Schema::dropIfExists('enable_trading_settings');
        }
    }
}
