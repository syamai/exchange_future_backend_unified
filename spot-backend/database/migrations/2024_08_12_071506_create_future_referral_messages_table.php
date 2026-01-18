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
        Schema::create('future_referral_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('buyer_id')->index();
            $table->unsignedInteger('seller_id')->index();
            $table->string('coin', 20);
            $table->decimal('buy_fee', 30, 10)->nullable();
            $table->decimal('sell_fee', 30, 10)->nullable();
            $table->date('executed_date')->nullable()->index();
            $table->tinyInteger('is_calculated_direct_commission')->default(0);
            $table->tinyInteger('is_calculated_partner_commission')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('future_referral_messages');
    }
};
