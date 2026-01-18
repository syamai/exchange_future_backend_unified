<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAirdropUserSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('airdrop_user_settings', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->unique();
            $table->string('email');
            $table->unsignedInteger('period')->nullable();     // days. If null, get default config from airdrop_settings table
            $table->decimal('unlock_percent', 7, 4)->nullable();  // percents. If null, get default config from airdrop_settings table

            $table->primary('user_id');

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
        Schema::dropIfExists('airdrop_user_settings');
    }
}
