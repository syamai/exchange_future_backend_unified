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
            // Index để tối ưu WHERE updated_at > ? + GROUP BY user_id
            $table->index(['user_id', 'updated_at'], 'idx_user_updated');
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
            $table->dropIndex('idx_user_updated');
        });
    }
};
