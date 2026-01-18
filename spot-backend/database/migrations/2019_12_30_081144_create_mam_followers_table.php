<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMamFollowersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mam_followers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('master_id');
            $table->unsignedInteger('user_id');
            $table->decimal('user_capital', 30, 15)->default(0);
            $table->decimal('user_balance', 30, 15)->default(0);
            $table->decimal('init_user_balance', 30, 15)->default(0);
            $table->decimal('performance_fee', 30, 15)->default(0);
            $table->dateTime('joined_at');
            $table->dateTime('left_at')->nullable()->default(null);
            $table->index(['master_id', 'user_id', 'left_at'], 'join_leave_history');
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
        Schema::dropIfExists('mam_followers');
    }
}
