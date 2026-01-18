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
        Schema::table('spot_commands', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->after('type_name')->nullable();
            $table->string('obj_id')->after('user_id')->nullable();
            $table->index(['type_name', 'user_id', 'obj_id']);
            $table->index('user_id');
            $table->index(['type_name', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spot_commands', function (Blueprint $table) {
            $table->dropIndex(['type_name', 'user_id', 'obj_id']);
            $table->dropColumn(['user_id']);
            $table->dropColumn(['obj_id']);
        });
    }
};
