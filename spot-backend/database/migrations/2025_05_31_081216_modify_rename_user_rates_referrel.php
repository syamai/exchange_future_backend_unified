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
        Schema::table('user_rates', function ($table) {
            $table->renameColumn('referrel_client_level', 'referral_client_level');
            $table->renameColumn('referrel_client_rate', 'referral_client_rate');
            $table->renameColumn('referrel_client_at', 'referral_client_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_rates', function ($table) {
            $table->renameColumn('referral_client_level', 'referrel_client_level');
            $table->renameColumn('referral_client_rate', 'referrel_client_rate');
            $table->renameColumn('referral_client_at', 'referrel_client_at');
        });
    }
};
