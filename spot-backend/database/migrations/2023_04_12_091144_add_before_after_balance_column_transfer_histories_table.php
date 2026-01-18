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
        Schema::table('transfer_history', function (Blueprint $table) {
            $table->string('before_balance')->after('amount')->nullable();
            $table->string('after_balance')->after('before_balance')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transfer_history', function (Blueprint $table) {
            $table->dropColumn('before_balance');
            $table->dropColumn('after_balance');
        });
    }
};
