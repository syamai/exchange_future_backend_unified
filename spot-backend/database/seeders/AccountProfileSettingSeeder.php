<?php

namespace Database\Seeders;

use App\Consts;
use App\Models\AccountProfileSetting;
use App\Models\CoinsConfirmation;
use App\Models\CoinSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountProfileSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Fetch all users with their related security settings
        $users = User::whereHas('securitySetting')->get();
        foreach ($users as $user) {
            if($user->AccountProfileSetting) continue;
            AccountProfileSetting::with('user')->updateOrCreate([
                'user_id' => $user->id,
                'spot_coin_pair_trade' => $this->setCoinPairTradeDefault()
            ]);
        }
    }

    public function setCoinPairTradeDefault()
    {
        $coin_pair = collect();
        $coin_pair_trade_eneble = Consts::COIN_PAIR_ENABLE_TRADING;

        // Retrieve the coin settings that are enabled
        $coinSetting = CoinSetting::whereHas('coinConfirmation')
            ->where('is_enable', 1)
            ->get(['coin', 'currency']);

        // Transform the enabled trading pairs from the constant
        $coin_pair_const = collect($coin_pair_trade_eneble)->transform(function ($pair) {
            list($coin, $currency) = explode("/", $pair);
            return ['coin' => strtolower($coin), 'currency' => strtolower($currency)];
        });

        // Iterate through each pair in coin_pair_trade_enable
        foreach ($coin_pair_const as $pair) {
            $exists = $coinSetting->contains(function ($item) use ($pair) {
                return strtolower($item['coin']) === $pair['coin'] && strtolower($item['currency']) === $pair['currency'];
            });
            // Use `put()` to add key-value pairs to the collection
            $coin_pair->put("{$pair['coin']}/{$pair['currency']}", $exists ? 1 : 0);
        }

        // Convert the collection to JSON
        return $coin_pair->toJson();
    }
}
