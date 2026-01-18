<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExampleAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('example_accounts', function (Blueprint $table) {
            $table->unsignedInteger('id')->unique();
            $table->decimal('balance', 30, 10)->default(0);
            $table->decimal('usd_amount', 30, 10)->default(0);
            $table->decimal('available_balance', 30, 10)->default(0);
            $table->string('blockchain_address')->nullable();
            $table->timestamps();
        });
//        Schema::create('margin_example_accounts', function (Blueprint $table) {
//            $table->unsignedInteger('id')->unique();
//            $table->decimal('balance', 30, 10)->default(0);
//            $table->decimal('usd_amount', 30, 10)->default(0);
//            $table->decimal('available_balance', 30, 10)->default(0);
//            $table->string('blockchain_address')->nullable();
//            $table->timestamps();
//        });
        Schema::create('spot_example_accounts', function (Blueprint $table) {
            $table->unsignedInteger('id')->unique();
            $table->decimal('balance', 30, 10)->default(0);
            $table->decimal('usd_amount', 30, 10)->default(0);
            $table->decimal('available_balance', 30, 10)->default(0);
            $table->string('blockchain_address')->nullable();
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
        Schema::dropIfExists('example_accounts');
//        Schema::dropIfExists('margin_example_accounts');
        Schema::dropIfExists('spot_example_accounts');
    }
}
