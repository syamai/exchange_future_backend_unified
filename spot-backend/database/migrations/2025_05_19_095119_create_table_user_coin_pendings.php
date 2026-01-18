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
        Schema::create('job_checkpoints', function (Blueprint $table) {
            $table->string('job')->primary();
            $table->unsignedBigInteger('last_calculated_at')->default(0); // milliseconds
            $table->timestamps();
        });

        Schema::create('user_coin_pendings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('currency'); // Ví dụ: USDT, BUSD
            $table->string('coin');     // Ví dụ: BTC, ETH

            // Tổng số lượng đang chờ khớp
            $table->decimal('pending_buy_quantity', 32, 12)->default(0);
            $table->decimal('pending_sell_quantity', 32, 12)->default(0);

            // Tổng giá trị USDT đang chờ khớp (quantity * price)
            $table->decimal('pending_buy_value', 32, 12)->default(0);
            $table->decimal('pending_sell_value', 32, 12)->default(0);

            // Tổng chênh lệch về lượng coin (âm nếu đang sell nhiều)
            $table->decimal('net_quantity', 32, 12)->virtualAs('pending_buy_quantity - pending_sell_quantity');

            // Mốc thời gian snapshot
            $table->unsignedBigInteger('last_updated_at')->nullable();

            // timestamps (optional, bạn có thể bỏ nếu dùng thủ công)
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();

            // Đảm bảo 1 user chỉ có 1 record cho mỗi coin/currency
            $table->unique(['user_id', 'currency', 'coin']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('job_checkpoints');
        Schema::dropIfExists('user_coin_pendings');
    }
};
