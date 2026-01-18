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
        Schema::create('liquidation_commissions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->useCurrent();
            $table->unsignedInteger('user_id')->index();
            $table->decimal('rate', 5, 2);
            $table->decimal('amount', 30, 10)->default(0);
            $table->timestamp('complete_at')->nullable();
            $table->enum('status', ['init','pending', 'canceled', 'completed'])->default('init')->index();
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
        Schema::dropIfExists('liquidation_commissions');
    }
};
