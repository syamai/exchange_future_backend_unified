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
            $table->string('status')->after('payload')->default('pending')->index();
            $table->text('payload_result')->after('status')->nullable();
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
            $table->dropColumn(['payload_result']);
            $table->dropColumn(['status']);
        });
    }
};
