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
        Schema::table('referrer_histories', function (Blueprint $table) {
            $table->tinyInteger('is_direct_ref')->default(0);
            $table->unsignedInteger('future_referral_message_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('referrer_histories', function (Blueprint $table) {
            $table->dropColumn(['is_direct_ref', 'future_referral_message_id']);
        });
    }
};
