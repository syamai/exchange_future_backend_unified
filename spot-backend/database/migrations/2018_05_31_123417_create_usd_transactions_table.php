<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsdTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('usd_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->decimal('amount', 30, 10)->default(0);
            $table->decimal('fee', 30, 10)->default(0);
            $table->string('bank_name');
            $table->string('bank_branch');
            $table->string('account_name');
            $table->string('account_no');
            $table->string('code')->nullable();
            $table->bigInteger('created_at');
            $table->bigInteger('updated_at');
            $table->string('status', 20); // array('success', 'pending', 'submitted', 'error', 'cancel'));

            $table->index('user_id');
            $table->index('created_at');
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('usd_transactions');
    }
}
