<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserKycTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_kyc', function (Blueprint $table) {
            $table->increments('id');
            $table->string('full_name');
            $table->string('id_front');
            $table->string('id_back');
            $table->string('id_selfie');
            $table->string('gender');
            $table->string('country');
            $table->string('id_number');
            $table->unsignedInteger('user_id');
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->enum('bank_status', ['unverified', 'verifing', 'creating', 'verified', 'rejected'])->default('unverified');
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
        Schema::dropIfExists('user_kyc');
    }
}
