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
        Schema::create('admin_krw_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('bank_id');
            $table->string('account_no');
            $table->string('account_name');
            $table->string('note')->nullable();
            $table->enum('status', ['disable', 'enable'])->default('enable');
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
        Schema::dropIfExists('admin_krw_bank_accounts');
    }
};
