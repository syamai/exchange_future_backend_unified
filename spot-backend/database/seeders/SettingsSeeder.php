<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        \DB::table('settings')->insert([
            ['key' => 'dividend_kyc_spot', 'value' => '0'],
            ['key' => 'self_trading_auto_dividend_spot', 'value' => '1'],
            ['key' => 'self_trading_auto_dividend_margin', 'value' => '1'],
            ['key' => 'self_trading_manual_dividend_spot', 'value' => '1'],
            ['key' => 'dividend_kyc_margin', 'value' => '0'],
            ['key' => 'self_trading_manual_dividend_margin', 'value' => '1'],
            ['key' => 'self_trading_volume_spot', 'value' => '1'],
            ['key' => 'trading_volume_kyc_spot', 'value' => '0'],
            ['key' => 'trading_volume_start_spot', 'value' => '1580528460000'],
            ['key' => 'trading_volume_end_spot', 'value' => '1706758860000'],
            ['key' => 'self_trading_volume_margin', 'value' => '1'],
            ['key' => 'trading_volume_kyc_margin', 'value' => '0'],
            ['key' => 'trading_volume_start_margin', 'value' => '1580528460000'],
            ['key' => 'trading_volume_end_margin', 'value' => '1706758860000'],
        ]);
    }
}
