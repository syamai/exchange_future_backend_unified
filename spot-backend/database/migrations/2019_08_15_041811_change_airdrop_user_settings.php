<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeAirdropUserSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('airdrop_user_settings', function (Blueprint $table) {
            //
            $table->string('period')->default('0')->change();     // days
            $table->decimal('unlock_percent', 7, 2)->default(100)->change();  // percents
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('airdrop_user_settings', function (Blueprint $table) {
            //
        });
    }
}
