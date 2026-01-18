<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMamTransferHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mam_transfer_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('master_id')->nullable();
            $table->decimal('amount', 30, 10)->default(0);
            $table->enum('reason', ['join', 'assign', 'revoke', 'reject', 'cancel', 'commission', 'expired', 'closed', 'created'])->default('join');
            $table->timestamps();

            $table->index('user_id');
            $table->index('master_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mam_transfer_history');
    }
}
