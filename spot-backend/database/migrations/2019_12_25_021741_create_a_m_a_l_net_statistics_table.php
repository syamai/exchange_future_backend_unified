<?php
 use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAMALNetStatisticsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('amal_net_statistics', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->decimal('amal_in', 30, 10)->nullable();
            $table->decimal('amal_out', 30, 10)->nullable();
            $table->date('statistic_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'statistic_date']);
            $table->index('user_id');
            $table->index('statistic_date');
        });
    }
    /**
    * Reverse the migrations.
    *
    * @return void
    */
    public function down()
    {
        Schema::dropIfExists('amal_net_statistics');
    }
}
