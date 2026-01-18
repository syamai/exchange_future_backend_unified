<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEnableWithdrawalSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('enable_withdrawal_settings')) {
            Schema::create('enable_withdrawal_settings', function (Blueprint $table) {
                $table->increments('id');

                $table->string('coin');
                $table->string('email');
                $table->enum('enable_withdrawal', ['enable', 'disable'])->default('enable');

                $table->timestamps();
                $table->index(['coin']);
                $table->index(['email']);
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
        if (Schema::hasTable('enable_withdrawal_settings')) {
            Schema::dropIfExists('enable_withdrawal_settings');
        }
    }
}
