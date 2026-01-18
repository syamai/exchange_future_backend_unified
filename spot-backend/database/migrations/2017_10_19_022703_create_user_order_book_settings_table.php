<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserOrderBookSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_order_book_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('currency', 20);
            $table->string('coin', 20);
            $table->unsignedTinyInteger('price_group');
            $table->unsignedTinyInteger('show_empty_group');
            $table->unsignedTinyInteger('click_to_order');
            $table->unsignedTinyInteger('order_confirmation');
            $table->unsignedTinyInteger('notification');
            $table->unsignedTinyInteger('notification_created');
            $table->unsignedTinyInteger('notification_matched');
            $table->unsignedTinyInteger('notification_canceled');
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'currency', 'coin']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_order_book_settings');
    }
}
