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
        Schema::table('user_trade_volume_per_days', function (Blueprint $table) {
            $table->dropColumn('amount');
            $table->integer('voucher_id');
            $table->decimal('volume', 30, 10);
            $table->integer('type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_trade_volume_per_days', function (Blueprint $table) {
            $table->decimal('amount', 30, 10);
            $table->dropColumn('voucher_id');
            $table->dropColumn('type');
        });
    }
};
