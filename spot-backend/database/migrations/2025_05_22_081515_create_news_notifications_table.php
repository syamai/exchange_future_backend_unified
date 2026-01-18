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
        Schema::create('news_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('admin_id');
            $table->unsignedInteger('cat_id');
            $table->string('link_event', 255);
            $table->string('title_en', 255);
            $table->text('content_en')->nullable();
            $table->string('title_vi', 255);
            $table->text('content_vi')->nullable();
            $table->enum('status', [Consts::NEWS_NOTIFICATION_STATUS_POSTED, Consts::NEWS_NOTIFICATION_STATUS_HIDDEN])->default(Consts::NEWS_NOTIFICATION_STATUS_HIDDEN);
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
        Schema::dropIfExists('news_notifications');
    }
};
