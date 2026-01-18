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
        Schema::create('social_news', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('admin_id');
            $table->string('link_page', 255);
            $table->string('domain_name', 255)->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('title_en', 255);
            $table->text('content_en')->nullable();
            $table->string('title_vi', 255);
            $table->text('content_vi')->nullable();
            $table->enum('status', [Consts::NEWS_NOTIFICATION_STATUS_POSTED, Consts::NEWS_NOTIFICATION_STATUS_HIDDEN])->default(Consts::NEWS_NOTIFICATION_STATUS_HIDDEN);
            $table->smallInteger('is_pin')->default(0);
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
        Schema::dropIfExists('social_news');
    }
};
