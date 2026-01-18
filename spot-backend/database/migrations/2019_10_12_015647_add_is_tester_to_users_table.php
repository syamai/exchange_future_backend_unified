<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIsTesterToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('users', 'is_tester')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('is_tester', ['active', 'inactive'])->after('status')->default('inactive');
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
        if (Schema::hasColumn('users', 'is_tester')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_tester');
            });
        }
    }
}
