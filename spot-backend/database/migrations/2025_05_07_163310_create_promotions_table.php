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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('content')->nullable();
            $table->text('thumbnail')->nullable();
            $table->json('categories')->nullable()->comment(
                '1-Futures; 2-Spot; 3-Copy Trading; 4-New User Exclusive; 5-Trading Tournament; 6-Lucky Draw; 7-Buy Crypto; 8-Airdrop'
            );
            $table->boolean('isPinned')->default(false)->comment('MAXIMUM Pinned: 5');
            $table->integer('pinnedPosition')->nullable()->comment('1 to 5 (First to Last)');
            $table->dateTime('starts_at')->nullable()->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('promotions');
    }
};
