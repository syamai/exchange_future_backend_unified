<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTotalSupplyFieldToAirdropSettingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('airdrop_settings', 'total_supply')) {
            Schema::table('airdrop_settings', function (Blueprint $table) {
                $table->decimal('total_supply', 30, 10)->default(210000000);
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
        if (Schema::hasColumn('airdrop_settings', 'total_supply')) {
            Schema::table('airdrop_settings', function (Blueprint $table) {
                $table->dropColumn('total_supply');
            });
        }
    }
}
