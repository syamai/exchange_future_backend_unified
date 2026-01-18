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
        Schema::table('report_daily_referral_client', function (Blueprint $table) {
            $table->unsignedBigInteger('referral_client_referrer_pass_trade_in')->after('referral_client_referrer_total')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_daily_referral_client', function (Blueprint $table) {
            $table->dropColumn(['referral_client_referrer_pass_trade_in']);
        });
    }
};
