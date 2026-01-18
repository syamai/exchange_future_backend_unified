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
        Schema::create('client_referrer_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->string('email');
            $table->decimal('amount', 30, 10)->default(0);
            $table->string('coin');
            $table->decimal('commission_rate', 5, 2);
            $table->unsignedInteger('transaction_owner');
            $table->string('transaction_owner_email');
            $table->string('type')->default(null);
            $table->decimal('usdt_value', 30,10)->default(0);
            $table->unsignedInteger('complete_transaction_id')->index();
            $table->date('executed_date')->index();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('client_referrer_histories');
    }
};
