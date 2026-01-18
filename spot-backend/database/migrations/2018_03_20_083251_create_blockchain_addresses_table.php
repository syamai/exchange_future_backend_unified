<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBlockchainAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('blockchain_addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('currency', 20);
            $table->string('blockchain_address');
            $table->string('blockchain_sub_address')->nullable();
            $table->string('address_id')->nulllable();
            $table->string('device_id')->nullable();
            $table->string('path');
            $table->tinyInteger('available')->default(1);
            $table->timestamps();

            $table->index('address_id');
            $table->index(['currency', 'available']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('blockchain_addresses');
    }
}
