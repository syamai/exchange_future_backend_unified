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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('type', array_column(\App\Enums\TypeVoucher::cases(), 'value'))->default(\App\Enums\TypeVoucher::SPOT->value);
            $table->decimal('amount', 30, 10)->default(0);
            $table->enum('status', array_column(\App\Enums\StatusVoucher::cases(), 'value'))->default(\App\Enums\StatusVoucher::AVAILABLE->value);
            $table->date('expires_date')->nullable();
            $table->decimal('number', 30, 10)->nullable();
            $table->decimal('conditions_use', 30, 10)->default(0);
            $table->decimal('expires_date_number', 30, 10)->default(0);
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
        Schema::dropIfExists('vouchers');
    }
};
