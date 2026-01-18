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
        Schema::create('total_prices', function (Blueprint $table) {
            $table->increments('id');
            $table->string('currency', 20);
            $table->string('coin', 20);
            $table->decimal('max_price', 30, 10);
            $table->decimal('min_price', 30, 10);
            $table->decimal('volume', 30, 10);
            $table->decimal('quote_volume', 30, 10);
            //$table->index(['currency', 'coin'], 'last_price_id');
            $table->unique(['currency', 'coin']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('total_prices');
    }
};
