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
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cat_id');
            $table->unsignedInteger('sub_cat_id');
            $table->string('title_en', 255);
            $table->text('content_en')->nullable();
            $table->string('title_vi', 255);
            $table->text('content_vi')->nullable();
            $table->string('title_ko', 255);
            $table->text('content_ko')->nullable();
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
        Schema::dropIfExists('faqs');
    }
};
