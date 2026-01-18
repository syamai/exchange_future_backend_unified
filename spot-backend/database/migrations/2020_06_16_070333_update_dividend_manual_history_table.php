<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDividendManualHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dividend_manual_history', function (Blueprint $table) {
            $table->unsignedInteger('contest_id')->nullable();
            $table->unsignedInteger('team_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $columns = ['contest_id', 'team_id'];
        foreach ($columns as $column) {
            if (Schema::hasColumn('dividend_manual_history', $column)) {
                Schema::table('dividend_manual_history', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
}
