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
        if (!Schema::hasTable('commission_balances')) {
            Schema::create('commission_balances', function (Blueprint $table) {
                $table->unsignedInteger('id')->unique();
                $table->decimal('balance', 30, 10)->default(0)->comment('Total commission');
                $table->decimal('available_balance', 30, 10)->default(0)->comment('Withdrawable commission');
                $table->decimal('withdrawn_balance', 30, 10)->default(0)->comment('Withdrawn commission');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('commission_balances');
    }
};
