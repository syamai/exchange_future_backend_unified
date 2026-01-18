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
        Schema::create('user_samsub_kyc', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name');
            $table->string('id_front')->nullable();
            $table->string('id_back')->nullable();
            $table->string('id_selfie')->nullable();
            $table->string('gender')->nullable();
            $table->string('country')->nullable();
            $table->string('id_number')->nullable();
            $table->unsignedInteger('user_id')->unique();
            $table->string('id_applicant')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->enum('bank_status', ['init', 'pending', 'prechecked', 'queued', 'completed', 'onHold'])->default('init');
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
        Schema::dropIfExists('user_samsub_kyc');
    }
};
