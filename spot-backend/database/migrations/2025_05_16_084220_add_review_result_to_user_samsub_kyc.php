<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_samsub_kyc', function (Blueprint $table) {
            $table->text('review_result')->nullable();
			DB::statement("ALTER TABLE user_samsub_kyc MODIFY COLUMN bank_status ENUM('init', 'pending', 'prechecked', 'queued', 'completed', 'onHold', 'rejected') DEFAULT 'init'");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_samsub_kyc', function (Blueprint $table) {
			$table->dropColumn(['review_result']);
        });
    }
};
