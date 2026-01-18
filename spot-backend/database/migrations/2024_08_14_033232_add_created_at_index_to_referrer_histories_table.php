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
            $table->index('created_at');
            $table->decimal('usdt_value', 30,10)->default(0);
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
            $table->dropIndex(['created_at']);
            $table->dropColumn('usdt_value');
        });
    }
};
