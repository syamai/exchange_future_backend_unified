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
        Schema::create('player_real_balance_report', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('uid')->nullable();
            $table->decimal('total_deposit', 30, 10)->nullable();
            $table->decimal('total_withdraw', 30, 10)->nullable();
            $table->decimal('pending_withdraw', 30, 10)->nullable();
            $table->decimal('closing_withdraw', 30, 10)->nullable();
            $table->decimal('total_volume', 30, 10)->nullable();
            $table->decimal('profit', 30, 10)->nullable();
            $table->decimal('roi', 10, 4)->nullable();
            $table->decimal('current_position', 30, 10)->nullable();
            $table->decimal('pending_position', 30, 10)->nullable();
            $table->decimal('total_fees_paid', 30, 10)->nullable();
            $table->decimal('fee_rebates_percent', 10, 4)->nullable();
            $table->decimal('fee_rebates_value', 30, 10)->nullable();
            $table->unsignedBigInteger('last_login_at')->nullable();
            $table->unsignedBigInteger('reported_at')->nullable();
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();

            $table->unique(['user_id', 'uid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('player_real_balance_report');
    }
};
