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
        Schema::table('future_referral_messages', function (Blueprint $table) {
            $table->dropColumn(['is_calculated_direct_commission', 'is_calculated_partner_commission']);
        });
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->dropColumn(['is_calculated_direct_commission', 'is_calculated_partner_commission']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('future_referral_messages', function (Blueprint $table) {
            $table->tinyInteger('is_calculated_direct_commission')->default(0);
            $table->tinyInteger('is_calculated_partner_commission')->default(0);
        });
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->tinyInteger('is_calculated_direct_commission')->default(0);
            $table->tinyInteger('is_calculated_partner_commission')->default(0);
        });
    }
};
