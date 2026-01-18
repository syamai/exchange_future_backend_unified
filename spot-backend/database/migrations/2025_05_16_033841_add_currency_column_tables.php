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
       Schema::table('gains_statistics_overview', function (Blueprint $table) {
           $table->string('currency')->after('register_date');
       });
       Schema::table('user_coin_holdings', function (Blueprint $table) {
           $table->string('currency')->after('user_id');
       });
       Schema::table('user_coin_holdings_logs', function (Blueprint $table) {
           $table->string('currency')->after('order_id');
       });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gains_statistics_overview', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
        Schema::table('user_coin_holdings', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
        Schema::table('user_coin_holdings_logs', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
