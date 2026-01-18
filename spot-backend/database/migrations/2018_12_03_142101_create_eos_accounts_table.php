<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEosAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('eos_accounts', function (Blueprint $table) {
            $table->unsignedInteger('id')->unique();
            $table->decimal('balance', 30, 10)->default(0);
            $table->decimal('usd_amount', 30, 10)->default(0);
            $table->decimal('available_balance', 30, 10)->default(0);
            $table->string('blockchain_address')->nullable();
            $table->string('blockchain_sub_address')->nullable();
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
        Schema::dropIfExists('eos_accounts');
    }
}
