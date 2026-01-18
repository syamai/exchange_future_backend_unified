<?php

namespace App;

use App\Models\CoinSetting;

class IdentifierHelper
{
    public static function generateUniqueIdentifier()
    {
        $microtime = microtime(true); // Get current microtime as a float
        $randomString = self::generateRandomString(config('identifier.length')); // Generate a random string of specified length

        return $microtime . "_" . $randomString;
    }

    public static function generateRandomString($length)
    {
        $characters = config('identifier.characters');
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }
    public function tradeTypes() {
        return [
            Consts::ORDER_TRADE_TYPE_SELL,
            Consts::ORDER_TRADE_TYPE_BUY
        ];
    }
    public function orderTypes() {
        return collect( [
            Consts::ORDER_TYPE_LIMIT,
            Consts::ORDER_TYPE_MARKET,
            Consts::ORDER_TYPE_STOP_LIMIT,
            Consts::ORDER_TYPE_STOP_MARKET,
            /*Consts::ORDER_STOP_TYPE_LIMIT,
            Consts::ORDER_STOP_TYPE_MARKET,
            Consts::ORDER_STOP_TYPE_TRAILING_STOP,
            Consts::ORDER_STOP_TYPE_TAKE_PROFIT_MARKET,
            Consts::ORDER_STOP_TYPE_TAKE_PROFIT_LIMIT,
            Consts::ORDER_STOP_TYPE_OCO,
            Consts::ORDER_STOP_TYPE_IFD*/
        ])->unique()->values();
    }

    public function statusOrdersOpen () {
        return collect([
            Consts::ORDER_STATUS_NEW,
            Consts::ORDER_STATUS_PENDING,
            Consts::ORDER_STATUS_EXECUTING,
            Consts::ORDER_STATUS_STOPPING,
        ])->unique()->values();
    }
    public function statusOrdersHistory() {
        return [
            Consts::ORDER_STATUS_CANCELED,
            Consts::ORDER_STATUS_EXECUTED,
//            Consts::ORDER_STATUS_EXECUTING
        ];
    }

    public function filterStatusCase($orderType) {
        return match ($orderType) {
            1 => ['open', 'pending','partial_filled'], //ORDER_STATUS
            2 => ['canceled','filled'],//['canceled','partial_filled','filled'], //ORDER_HISTORY_STATUS,
            11 => 'open',
            12 => 'pending',
            13 => 'partial_filled',
            21 => 'canceled',
            23 => 'filled',
            default => []
        };
    }
    public function pairCoinEnable() {
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

        $coin_pair = collect($coin_pair)->filter(function ($v) {
                return $v == 1;
            })->keys();

        $coin = $currency = collect();
        collect($coin_pair)->each(function ($item) use($coin, $currency) {
            list($co, $cur) = explode("/", $item);
            $coin->push($co);
            $currency->push($cur);
        });

        $pair = collect();
        $pair->put('coins', $coin->unique()->values());
        $pair->put('currency', $currency->unique()->values());

        return $pair;
    }
}