<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeAirdropSetting extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('airdrop_settings', function (Blueprint $table) {
            $table->decimal('remaining', 30, 10)->default(0); // Remaining Amount
            $table->decimal('total_paid', 30, 10)->default(0); // Total has paid
            $table->string('period')->default('0')->change();     // days
            $table->decimal('unlock_percent', 7, 2)->default(100)->change();  // percents
            $table->decimal('total_supply', 30, 10)->default(210000000);
        });
        if (Schema::hasColumn('airdrop_settings', 'btc_amount')) {
            Schema::table('airdrop_settings', function (Blueprint $table) {
                $table->dropColumn('btc_amount');
            });
        }
        if (Schema::hasColumn('airdrop_settings', 'eth_amount')) {
            Schema::table('airdrop_settings', function (Blueprint $table) {
                $table->dropColumn('eth_amount');
            });
        }
        if (Schema::hasColumn('airdrop_settings', 'amal_amount')) {
            Schema::table('airdrop_settings', function (Blueprint $table) {
                $table->dropColumn('amal_amount');
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
        Schema::table('airdrop_settings', function (Blueprint $table) {
            //
        });
    }
}
