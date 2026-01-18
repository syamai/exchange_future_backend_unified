<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Consts;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('admin_id');
            $table->unsignedInteger('cat_id');
            $table->string('static_url', 255)->unique();
            $table->string('thumbnail_url')->nullable();
            $table->string('title_en', 255);
            $table->text('seo_title_en')->nullable();
            $table->text('meta_keywords_en')->nullable();
            $table->text('seo_description_en')->nullable();
            $table->text('content_en')->nullable();
            $table->string('title_vi', 255);
            $table->text('seo_title_vi')->nullable();
            $table->text('meta_keywords_vi')->nullable();
            $table->text('seo_description_vi')->nullable();
            $table->text('content_vi')->nullable();
            $table->boolean('is_pin')->default(false);
            $table->enum('status', [Consts::STATUS_POSTED, Consts::STATUS_HIDDEN])->default(Consts::STATUS_HIDDEN);
            $table->softDeletes();
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
        Schema::dropIfExists('blogs');
    }
};
