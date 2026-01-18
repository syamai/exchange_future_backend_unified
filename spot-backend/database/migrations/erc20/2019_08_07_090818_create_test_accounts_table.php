<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('test_accounts', function (Blueprint $table) {
            $table->unsignedInteger('id')->unique();
            $table->decimal('balance', 30, 10)->default(0);
            $table->decimal('usd_amount', 30, 10)->default(0);
            $table->decimal('available_balance', 30, 10)->default(0);
            $table->string('blockchain_address')->nullable();
            $table->timestamps();
        });
//        Schema::create('margin_test_accounts', function (Blueprint $table) {
//            $table->unsignedInteger('id')->unique();
//            $table->decimal('balance', 30, 10)->default(0);
//            $table->decimal('usd_amount', 30, 10)->default(0);
//            $table->decimal('available_balance', 30, 10)->default(0);
//            $table->string('blockchain_address')->nullable();
//            $table->timestamps();
//        });
        Schema::create('spot_test_accounts', function (Blueprint $table) {
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
        Schema::dropIfExists('test_accounts');
//        Schema::dropIfExists('margin_test_accounts');
        Schema::dropIfExists('spot_test_accounts');
    }
}
