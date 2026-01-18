<?php

use App\Utils;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('transaction_id')->unique();
            $table->unsignedInteger('user_id');

            $table->string('tx_hash')->nullable();
            $table->string('currency', 20);
            $table->decimal('amount', 30, 10)->default(0);
            $table->decimal('fee', 30, 10)->default(0);
            $table->string('status', 20);

            $table->string('from_address', 256)->nullable();
            $table->string('to_address', 256)->nullable();

            $table->string('blockchain_address', 256)->nullable();
            $table->string('blockchain_sub_address', 256)->nullable();

            $table->string('verify_code', 30)->nullable();
            $table->boolean('is_external')->default(false);
            $table->unsignedInteger('approved_by')->nullable();
            $table->bigInteger('approve_at')->nullable();
            $table->unsignedInteger('deny_by')->nullable();
            $table->bigInteger('deny_at')->default(0);
            $table->unsignedInteger('send_confirmer1')->nullable();
            $table->unsignedInteger('send_confirmer2')->nullable();
            $table->unsignedInteger('sent_by')->nullable();
            $table->bigInteger('sent_at')->nullable();
            $table->unsignedInteger('reject_by')->nullable();
            $table->bigInteger('reject_at')->nullable();
            $table->bigInteger('cancel_at')->nullable();
            $table->text('remarks')->nullable();
            $table->text('error_detail')->nullable();

            $table->date('transaction_date');
            $table->bigInteger('created_at');
            $table->bigInteger('updated_at');

            $table->index(['user_id', 'currency']);
            $table->index(['user_id', 'created_at', 'currency']);
            $table->index('transaction_id');
            $table->index('created_at');

            $table->index(['transaction_date', 'currency'], 'transaction_fee_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
