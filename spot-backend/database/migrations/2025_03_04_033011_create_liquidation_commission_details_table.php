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
        Schema::create('liquidation_commission_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidation_commission_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('user_id')->index();
            $table->decimal('amount', 30, 10)->default(0);
            $table->timestamps();

            $table->unique(['liquidation_commission_id', 'user_id'], "liquidation_commission_id_user_id_unique");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('liquidation_commission_details');
    }
};
