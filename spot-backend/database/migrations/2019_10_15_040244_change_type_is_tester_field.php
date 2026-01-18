<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTypeIsTesterField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('users', 'is_tester')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_tester');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('is_tester', ['active', 'waiting', 'inactive'])->after('status')->default('inactive');
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
