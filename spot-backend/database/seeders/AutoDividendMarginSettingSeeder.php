<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Consts;
use App\Models\AutoDividendSetting;
use App\Service\Margin\InstrumentService;

class AutoDividendMarginSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('dividend_auto_settings')->where(["setting_for" => "margin"])->update(["is_show" => 0]);

        $instruments = app(InstrumentService::class)->getAllInstrument();

        foreach ($instruments as $instrument) {
            AutoDividendSetting::updateOrCreate([
                'market' => strtolower($instrument["root_symbol"]),
                'coin' => strtoupper($instrument["symbol"]),
            ], [
                // 'enable' => false,
                'market' => strtolower($instrument["root_symbol"]),
                'coin' => strtoupper($instrument["symbol"]),
                // 'time_from' => null,
                // 'time_to' => null,
                'payfor' => Consts::TYPE_MAIN_BALANCE,
                'payout_coin' => 'AMAL',
                'payout_amount' => 0,
                'setting_for' => 'margin',
                // 'lot' => 0,
                'is_show' => strtolower($instrument["state"]) == 'open' ? 1 : 0
            ]);
        }
    }
}
