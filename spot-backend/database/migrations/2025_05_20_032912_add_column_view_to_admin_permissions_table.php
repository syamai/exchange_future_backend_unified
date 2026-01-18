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
        Schema::table('admin_permissions', function (Blueprint $table) {
            $table->smallInteger('view')->default(0)->after('name');
			$table->smallInteger('create')->default(0)->after('view');
			$table->smallInteger('edit')->default(0)->after('create');
			$table->smallInteger('delete')->default(0)->after('edit');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            $table->dropColumn(['view', 'create', 'edit', 'delete']);
        });
    }
};
