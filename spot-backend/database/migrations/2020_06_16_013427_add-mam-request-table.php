<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMamRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('mam_requests', 'status')) {
            Schema::table('mam_requests', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        Schema::table('mam_requests', function (Blueprint $table) {
            $table->enum('status', ['approved', 'canceled', 'executed', 'rejected', 'submitted', 'failed'])->default('submitted');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
