<?php

namespace App\Models;

use App\Consts;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountProfileSetting extends Model
{
    use HasFactory;

    protected $table = 'account_profile_settings';
    protected $fillable = ['user_id', 'spot_trade_allow', 'spot_trading_fee_allow', 'spot_market_marker_allow', 'spot_coin_pair_trade', 'spot_coin_pair_trade'];

    public function user() {
        return $this->belongsTo(User::class, 'id', 'user_id');
    }
    public static function setCoinPairTradeDefault()
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
