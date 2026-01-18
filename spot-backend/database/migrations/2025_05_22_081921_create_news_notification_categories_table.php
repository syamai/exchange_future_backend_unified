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
        Schema::create('news_notification_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title_en', 255);
            $table->string('title_vi', 255);
            $table->enum('status', ['disable', 'enable'])->default('enable')->index();
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
        Schema::dropIfExists('news_notification_categories');
    }
};
