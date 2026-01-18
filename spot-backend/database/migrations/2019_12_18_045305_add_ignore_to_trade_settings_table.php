<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddIgnoreToTradeSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('enable_trading_settings', function (Blueprint $table) {
            DB::statement("ALTER TABLE enable_trading_settings MODIFY COLUMN enable_trading ENUM('enable', 'waiting', 'disable', 'ignore') DEFAULT 'enable'");
            $table->string('ignore_expired_at')->nullable()->default('');
            $table->boolean('is_beta_tester')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('enable_trading_settings', function (Blueprint $table) {
            //
        });
    }
}
