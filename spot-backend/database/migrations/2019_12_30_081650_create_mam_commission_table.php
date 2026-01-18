<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMamCommissionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mam_commission', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('master_id');
            $table->dateTime('interval');
            $table->decimal('entry_capital', 30, 15)->default(0);
            $table->decimal('exit_capital', 30, 15)->default(0);
            $table->decimal('entry_balance', 30, 15)->default(0);
            $table->decimal('exit_balance', 30, 15)->default(0);
            $table->unsignedInteger('followers')->default(0);
            $table->decimal('commission', 30, 15)->default(0);
            $table->decimal('realised_pnl', 30, 15)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mam_commission');
    }
}
