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
        Schema::table('referrer_histories', function (Blueprint $table) {
            $table->string('symbol')->nullable();
            $table->string('asset_future')->nullable();
            $table->string('order_transaction_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('referrer_histories', function (Blueprint $table) {
            $table->dropColumn('symbol');
            $table->dropColumn('asset_future');
        });
    }
};
