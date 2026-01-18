<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWithdrawalLimitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('withdrawal_limits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('security_level');
            $table->string('currency', 20);
            $table->decimal('limit', 30, 10);
            $table->decimal('daily_limit', 30, 10);
            $table->decimal('fee', 30, 10);
            $table->string('minium_withdrawal')->nullable();
            $table->integer('days')->default(0);
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
        Schema::dropIfExists('withdrawal_limits');
    }
}
