<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->unsignedTinyInteger('security_level')->default(1);
            $table->boolean('restrict_mode')->default(false);
            $table->string('password');
            $table->unsignedTinyInteger('max_security_level')->default(1);
            $table->string('google_authentication')->nullable();
            $table->rememberToken();

            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->string('hp')->nullable();
            $table->string('bank')->nullable();
            $table->string('real_account_no')->nullable();
            $table->string('virtual_account_no')->nullable();
            $table->string('account_note')->nullable();
            $table->unsignedInteger('referrer_id')->nullable();
            $table->string('referrer_code')->nullable();
            $table->enum('type', ['bot', 'referrer', 'normal'])->default('normal');
            $table->string('phone_no', 50)->nullable();
            $table->string('memo')->nullable();

            $table->index('referrer_id');
            $table->index('phone_no');
            $table->index(['real_account_no', 'bank']);

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
        Schema::dropIfExists('users');
    }
}
