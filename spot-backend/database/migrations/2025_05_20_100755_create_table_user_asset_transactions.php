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
        Schema::create('user_asset_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('currency')->nullable();
            $table->string('status')->nullable();
            $table->decimal('price_real', 30, 10)->nullable();

            $table->decimal('total_deposit', 30, 10)->nullable();
            $table->decimal('total_pending_deposit', 30, 10)->nullable();
            $table->decimal('total_cancel_deposit', 30, 10)->nullable();

            $table->decimal('total_withdraw', 30, 10)->nullable();
            $table->decimal('total_pending_withdraw', 30, 10)->nullable();
            $table->decimal('total_cancel_withdraw', 30, 10)->nullable();

            $table->decimal('deposit_value', 30, 10)->nullable();
            $table->decimal('pending_deposit_value', 30, 10)->nullable();
            $table->decimal('cancel_deposit_value', 30, 10)->nullable();

            $table->decimal('withdraw_value', 30, 10)->nullable();
            $table->decimal('pending_withdraw_value', 30, 10)->nullable();
            $table->decimal('cancel_withdraw_value', 30, 10)->nullable();

            $table->string('category');
            $table->unsignedBigInteger('reported_at')->nullable();
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_asset_transactions');
    }
};
