<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Consts;

class CreateAirdropSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('airdrop_settings', function (Blueprint $table) {
            $table->increments('id');

            $table->boolean('enable');
            $table->string('currency', 5)->default(Consts::CURRENCY_BTC);  // btc,eth,amal
            $table->unsignedInteger('period')->default(0);     // days
            $table->decimal('unlock_percent', 7, 4)->default(100);  // percents
            $table->decimal('payout_amount', 30, 10)->default(0);
            $table->string('payout_time', 10)->default('00:00'); // Hour in day HH:ii
            $table->decimal('btc_amount', 30, 10)->default(0); // Remaining BTC Amount
            $table->decimal('eth_amount', 30, 10)->default(0); // Remaining ETH Amount
            $table->decimal('amal_amount', 30, 10)->default(0); // Remaining AMAL Amount
            $table->decimal('min_hold_amal', 30, 10)->default(0); // Min AMAL Balance hold to bonus
            $table->string('status'); // Setting which are using has status is "active"

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
        Schema::dropIfExists('airdrop_settings');
    }
}
