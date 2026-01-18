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
        Schema::create('report_daily_referral_client', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('uid')->nullable();
            $table->unsignedBigInteger('referral_client_referrer_total')->default(0);
            $table->unsignedBigInteger('referral_client_registration_at')->nullable();
            $table->decimal('referral_client_rate', 10, 4)->default(0);
            $table->decimal('referral_client_trade_volume_value', 30, 10)->default(0);
            $table->decimal('referral_client_commission_value', 30, 10)->default(0);
            $table->tinyInteger('referral_client_tier')->default(0);
            $table->unsignedBigInteger('reported_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'uid', 'reported_at'], 'uniq_user_uid_reported_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('report_daily_referral_client');
    }
};
