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
        Schema::create('report_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedInteger('user_id')->index();
            $table->decimal('volume', 30, 10)->default(0);
            $table->decimal('fee', 30, 10)->default(0);
            $table->decimal('commission', 30, 10)->default(0);
            $table->decimal('direct_commission', 30, 10)->default(0);
            $table->decimal('indirect_commission', 30, 10)->default(0);
            $table->timestamps();

            $table->unique(['date', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('report_transactions');
    }
};
