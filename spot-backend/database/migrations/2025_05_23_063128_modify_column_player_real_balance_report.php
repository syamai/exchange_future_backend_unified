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
        Schema::table('player_real_balance_report', function (Blueprint $table) {
            $table->dropColumn('closing_withdraw');
            $table->decimal('closing_balance', 30, 10)->after('pending_withdraw')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('player_real_balance_report', function (Blueprint $table) {
            $table->dropColumn('closing_balance');
            $table->decimal('closing_withdraw', 30, 10)->after('pending_withdraw')->nullable();
        });
    }
};
