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
        Schema::table('user_rates', function($table) {
            $table->tinyInteger('referrel_client_level')->after('id')->default(0);
            $table->decimal('referrel_client_rate', 10, 4)->after('referrel_client_level')->default(0);
            $table->unsignedBigInteger('referrel_client_at')->after('referrel_client_rate')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_rates', function($table) {
            $table->dropColumn(['referrel_client_level', 'referrel_client_rate', 'referrel_client_at']);
        });
    }
};
