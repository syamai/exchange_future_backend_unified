<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Consts;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('admins', function (Blueprint $table) {
			DB::statement("ALTER TABLE admins MODIFY COLUMN role ENUM('".Consts::ROLE_SUPER_ADMIN . "', '" . Consts::ROLE_ADMIN . "', '" . Consts::ROLE_ACCOUNT . "', '" . Consts::ROLE_MARKETING . "', '" . Consts::ROLE_OPERATOR . "') DEFAULT '" . Consts::ROLE_ADMIN . "'");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('admins', function (Blueprint $table) {
            //
        });
    }
};
