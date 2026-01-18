<?php

use App\Consts;
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
        Schema::create('chatbots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('admin_id');
            $table->unsignedInteger('type_id');
            $table->unsignedInteger('cat_id');
            $table->unsignedInteger('sub_cat_id');
            $table->string('link_page', 255)->nullable();
            $table->string('question_en', 255);
            $table->text('answer_en')->nullable();
            $table->string('question_vi', 255);
            $table->text('answer_vi')->nullable();
            $table->enum('status', [Consts::ENABLE_STATUS, Consts::DISABLE_STATUS])->default(Consts::DISABLE_STATUS);
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
        Schema::dropIfExists('chatbots');
    }
};
