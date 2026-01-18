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
        Schema::create('report_referral_commission_ranking', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('uid')->index();
            $table->unsignedBigInteger('rank')->default(0);
            $table->unsignedInteger('referrals')->default(0);
            $table->unsignedBigInteger('registration_at')->nullable();
            $table->decimal('total_volume_value', 30, 10)->default(0);
            $table->decimal('total_commission_value', 30, 10)->default(0);
            $table->tinyInteger('tier')->default(0);
            $table->unsignedBigInteger('reported_at')->nullable();
            $table->unsignedTinyInteger('week');
            $table->unsignedSmallInteger('year');

            $table->timestamps();

            $table->unique(['user_id', 'week', 'year'], 'uniq_user_week_year');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('report_referral_commission_ranking');
    }
};
