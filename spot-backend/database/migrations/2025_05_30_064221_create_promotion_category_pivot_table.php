<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('promotion_category_pivot')) {
            Schema::create('promotion_category_pivot', function (Blueprint $table) {
                $table->id();
                $table->foreignId('promotion_id')->constrained()->onDelete('cascade');
                $table->foreignId('promotion_category_id')->constrained()->onDelete('cascade');
                $table->timestamps();

                $table->unique(['promotion_id', 'promotion_category_id'], 'promo_cat_pivot_unique');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('promotion_category_pivot');
    }
}; 