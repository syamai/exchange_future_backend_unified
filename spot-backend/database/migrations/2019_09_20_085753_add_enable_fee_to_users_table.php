<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEnableFeeToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('users', 'enable_fee')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('enable_fee', ['enable', 'disable'])->after('status')->default('enable');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('users', 'enable_fee')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('enable_fee');
            });
        }
    }
}
