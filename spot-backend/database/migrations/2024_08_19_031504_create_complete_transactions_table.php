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
        Schema::create('complete_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_id')->index()->nullable();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('exchange_user')->index();
            $table->unsignedInteger('order_transaction_id')->index()->nullable();
            $table->unsignedInteger('future_referral_message_id')->index()->nullable();
            $table->string('email');
            $table->string('type', 20)->comment('spot||future');
            $table->enum('transaction_type', ['buy', 'sell']);
            $table->string('currency', 20);
            $table->string('coin', 20)->index();
            $table->string('asset_future')->nullable();
            $table->string('symbol_future')->nullable();
            $table->decimal('price', 30, 10)->nullable();
            $table->decimal('quantity', 30, 10)->nullable();
            $table->decimal('amount', 30, 10);
            $table->decimal('fee', 30, 10);
            $table->decimal('price_usdt', 30, 10)->default(1);
            $table->decimal('amount_usdt', 30, 10);
            $table->decimal('fee_usdt', 30, 10);
            $table->date('executed_date');
            $table->tinyInteger('is_calculated_direct_commission')->default(0)->index();
            $table->tinyInteger('is_calculated_partner_commission')->default(0)->index();
            $table->timestamps();

            $table->index(['executed_date', 'user_id']);
            $table->index(['asset_future', 'type']);
            $table->index(['coin', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('complete_transactions');
    }
};
