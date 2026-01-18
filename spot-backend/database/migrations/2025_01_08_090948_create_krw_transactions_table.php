<?php

use App\Consts;
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
        Schema::create('krw_transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->enum('type', [Consts::TRANSACTION_TYPE_DEPOSIT, Consts::TRANSACTION_TYPE_WITHDRAW])->index();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_no');
            $table->decimal('exchange_rate', 30, 10)->default(0);
            $table->decimal('amount_usdt', 30, 10)->default(0);
            $table->decimal('amount_krw', 30, 10)->default(0);
            $table->decimal('fee', 30, 10)->default(0);
            $table->string('status', 20); // array('success', 'pending', 'submitted', 'error', 'cancel'));
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
        Schema::dropIfExists('krw_transactions');
    }
};
