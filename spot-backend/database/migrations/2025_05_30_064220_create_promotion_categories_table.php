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
        if (!Schema::hasTable('promotion_categories')) {
            Schema::create('promotion_categories', function (Blueprint $table) {
                $table->id();
                $table->json('name');
                $table->string('key')->unique();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('updated_by')
                    ->references('id')
                    ->on('admins')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('promotion_categories');
    }
};
