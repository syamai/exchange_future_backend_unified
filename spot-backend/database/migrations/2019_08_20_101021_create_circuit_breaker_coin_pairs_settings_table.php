<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCircuitBreakerCoinPairsSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('circuit_breaker_coin_pair_settings')) {
            Schema::create('circuit_breaker_coin_pair_settings', function (Blueprint $table) {
                $table->increments('id');

                $table->string('currency');
                $table->string('coin');
                $table->decimal('range_listen_time', 20, 5)->default(1);   // Default: 1 hour
                $table->decimal('circuit_breaker_percent', 30, 10)->default(0);
                $table->decimal('block_time', 20, 5)->default(1);  // Default: 1 hour
                $table->enum('status', ['disable', 'enable'])->default('enable');
                $table->bigInteger('locked_at')->nullable();
                $table->bigInteger('unlocked_at')->nullable();
                $table->bigInteger('last_order_transaction_id')->nullable();
                $table->decimal('last_price', 30, 10)->nullable(); // Last price at lock trading by auto
                $table->boolean('block_trading')->default(false);

                $table->timestamps();
                $table->index(['currency', 'coin']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('circuit_breaker_coin_pair_settings');
    }
}
