<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Consts;

class CreateAirdropHistoryLockBalanceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('airdrop_history_lock_balance', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedInteger('user_id');
            $table->string('email');
            $table->string('currency', 5)->default(Consts::CURRENCY_AMAL);
            $table->string('status');
            $table->decimal('total_balance', 30, 10)->default(0);
            $table->decimal('amount', 30, 11)->default(0);
            $table->decimal('unlocked_balance', 30, 11)->default(0);
            $table->date('last_unlocked_date')->nullable();

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
        Schema::dropIfExists('airdrop_history_lock_balance');
    }
}
