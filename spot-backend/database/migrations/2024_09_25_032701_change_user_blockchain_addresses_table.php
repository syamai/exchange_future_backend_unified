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
        Schema::table('user_blockchain_addresses', function (Blueprint $table) {
            //$table->dropUnique(['blockchain_address']);
            $table->unique(['currency', 'network_id', 'blockchain_address'], 'network_coin_blockchain_address');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_blockchain_addresses', function (Blueprint $table) {
            $table->dropUnique('network_coin_blockchain_address');
            //$table->unique(['blockchain_address']);
        });
    }
};
