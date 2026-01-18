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
        
        Schema::create('partner_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index();
            $table->string('type', 2)->index();
            $table->string('detail', 255)->nullable();
            $table->string('old', 255);
            $table->string('new', 255);
            $table->enum('status', [0, 1, 2])->comment('0:Pending, 1:Approve, 2:Reject')->index();
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('created_by')->nullable()->index();
            $table->unsignedInteger('processed_by')->nullable();
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
        Schema::dropIfExists('partner_requests');
    }
};
