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
            $table->decimal('amount', 30, 10)->default(0);
            $table->string('symbol')->nullable();
            $table->decimal('rate_with_usdt', 30, 10)->default(1);
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
            $table->dropColumn(['amount', 'symbol', 'rate_with_usdt']);
        });
    }
};
