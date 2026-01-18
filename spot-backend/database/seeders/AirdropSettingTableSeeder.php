<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AirdropSetting;
use App\Consts;

class AirdropSettingTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        AirdropSetting::truncate();
        $this->createNew();
    }

    private function createNew()
    {
        AirdropSetting::create([
            'enable' => true,
            'currency' => Consts::CURRENCY_BTC,
            'period' => 30, // days
            'unlock_percent' => 10, // 10%
            'payout_amount' => 0,
            'payout_time' => '00:00:00',
            'btc_amount' => 0,
            'eth_amount' => 0,
            'amal_amount' => 0,
            'min_hold_amal' => 500000,
            'created_at' => now(),
            'updated_at' => now(),
            'status' => Consts::AIRDROP_SETTING_ACTIVE
        ]);

        AirdropSetting::create([
            'enable' => true,
            'currency' => Consts::CURRENCY_AMAL,
            'period' => 30, // days
            'unlock_percent' => 10, // 10%
            'payout_amount' => 0,
            'payout_time' => '00:00:00',
            'btc_amount' => 0,
            'eth_amount' => 0,
            'amal_amount' => 0,
            'min_hold_amal' => 500000,
            'created_at' => now(),
            'updated_at' => now(),
            'status' => Consts::AIRDROP_SETTING_INACTIVE
        ]);

        AirdropSetting::create([
            'enable' => true,
            'currency' => Consts::CURRENCY_ETH,
            'period' => 30, // days
            'unlock_percent' => 10, // 10%
            'payout_amount' => 0,
            'payout_time' => '00:00:00',
            'btc_amount' => 0,
            'eth_amount' => 0,
            'amal_amount' => 0,
            'min_hold_amal' => 500000,
            'created_at' => now(),
            'updated_at' => now(),
            'status' => Consts::AIRDROP_SETTING_INACTIVE
        ]);
    }
}
