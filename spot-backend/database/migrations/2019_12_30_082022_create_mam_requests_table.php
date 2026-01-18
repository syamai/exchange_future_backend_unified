<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMamRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mam_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('master_id');
            $table->enum('type', ['assign', 'join', 'revoke'])->default('assign');
            $table->enum('status', ['approved', 'canceled', 'executed', 'rejected', 'submitted'])->default('submitted');
            $table->decimal('amount', 30, 10)->default(0);
            $table->enum('revoke_type', ['profit', 'partial', 'all'])->default('profit');
            $table->string('note')->nullable();
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
        Schema::dropIfExists('mam_requests');
    }
}
