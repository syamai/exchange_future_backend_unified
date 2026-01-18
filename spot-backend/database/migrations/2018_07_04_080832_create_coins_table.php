<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coins', function (Blueprint $table) {
            $table->increments('id');
            $table->string('coin', 20);
            $table->longText('icon_image')->nullable();
            $table->string('name', 100);
            $table->integer('confirmation')->default(1);
            $table->string('type');
            $table->string('sub_address_name', 50)->nullable();
            $table->string('trezor_coin_shortcut', 20);
            $table->string('trezor_address_path', 50);
            $table->enum('env', ['testnet', 'mainnet'])->nullable();
            $table->string('contract_address')->nullable();
            $table->string('transaction_tx_path')->nullable();
            $table->string('transaction_explorer')->nullable();
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
        Schema::dropIfExists('coins');
    }
}
