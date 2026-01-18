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
        Schema::create('tmp_prices', function (Blueprint $table) {
            $table->increments('id');
            $table->string('currency', 20);
            $table->string('coin', 20);
            $table->decimal('price', 30, 10);
            $table->decimal('quantity', 30, 10);
            $table->decimal('amount', 30, 10);
            $table->unsignedInteger('is_crawled')->default(0);
            $table->bigInteger('created_at')->index();

            $table->index('price');
            $table->index(['currency', 'coin'], 'last_price_id');
            $table->index(['currency', 'coin', 'created_at'], 'last_price');
            $table->index(['currency', 'coin', 'created_at', 'price'], 'max_price');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tmp_prices');
    }
};
