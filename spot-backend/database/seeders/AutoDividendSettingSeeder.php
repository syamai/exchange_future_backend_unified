<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Consts;
use Carbon\Carbon;
use App\Models\AutoDividendSetting;
use App\Http\Services\MasterdataService;
use App\Service\Margin\InstrumentService;

class AutoDividendSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('dividend_auto_settings')->truncate();

        $curencyCoins = MasterdataService::getOneTable('coin_settings');
        foreach ($curencyCoins as $currencyCoin) {
            AutoDividendSetting::create([
                'enable' => false,
                'market' => $currencyCoin->currency,
                'coin' => $currencyCoin->coin,
                'time_from' => null,
                'time_to' => null,
                'payfor' => Consts::TYPE_MAIN_BALANCE,
                'payout_coin' => 'AMAL',
                'payout_amount' => 0,
                'setting_for' => 'spot',
                'lot' => 0
            ]);
        }

        $instruments = app(InstrumentService::class)->getAllInstrument();

        foreach ($instruments as $instrument) {
            AutoDividendSetting::create([
                'enable' => false,
                'market' => strtolower($instrument["root_symbol"]),
                'coin' => strtoupper($instrument["symbol"]),
                'time_from' => null,
                'time_to' => null,
                'payfor' => Consts::TYPE_MAIN_BALANCE,
                'payout_coin' => 'AMAL',
                'payout_amount' => 0,
                'setting_for' => 'margin',
                'lot' => 0
            ]);
        }
    }
}
