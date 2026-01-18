<?php

use App\Models\OrderTransaction;
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
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->tinyInteger('is_calculated_direct_commission')->default(0);
            $table->tinyInteger('is_calculated_partner_commission')->default(0);
            $table->decimal('currency_usdt_price', 30, 10)->default(1);
        });

        OrderTransaction::query()->update(['is_calculated_direct_commission' => 1, 'is_calculated_partner_commission' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->dropColumn(['is_calculated_direct_commission', 'is_calculated_partner_commission', 'currency_usdt_price']);
        });
    }
};
