<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCdtAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cdt_accounts', function (Blueprint $table) {
            $table->unsignedInteger('id')->unique();
            $table->decimal('balance', 30, 10)->default(0);
            $table->decimal('usd_amount', 30, 10)->default(0);
            $table->decimal('available_balance', 30, 10)->default(0);
            $table->string('blockchain_address')->nullable();
            $table->timestamps();
        });
//        Schema::create('margin_cdt_accounts', function (Blueprint $table) {
//            $table->unsignedInteger('id')->unique();
//            $table->decimal('balance', 30, 10)->default(0);
//            $table->decimal('usd_amount', 30, 10)->default(0);
//            $table->decimal('available_balance', 30, 10)->default(0);
//            $table->string('blockchain_address')->nullable();
//            $table->timestamps();
//        });
        Schema::create('spot_cdt_accounts', function (Blueprint $table) {
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
        Schema::dropIfExists('cdt_accounts');
//        Schema::dropIfExists('margin_cdt_accounts');
        Schema::dropIfExists('spot_cdt_accounts');
    }
}
