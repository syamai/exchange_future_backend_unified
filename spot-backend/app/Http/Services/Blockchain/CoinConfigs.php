<?php

namespace App\Http\Services\Blockchain;

use Illuminate\Support\Facades\DB;

class CoinConfigs
{
    static function getCoins()
    {
//        return [
//            'eth' => new Coin(
//                'eth',
//                config('blockchain.wallet_id.eth'),
//                '1000000000000000000'
//            ),
//            'btc' => new Coin(
//                'btc',
//                config('blockchain.wallet_id.btc'),
//                '100000000'
//            ),
//            'amal' => new Coin(
//                'amal',
//                config('blockchain.wallet_id.amal'),
//                '100000000',
//                'eth_token'
//            ),
//            'xrp' => new Coin(
//                'xrp',
//                config('blockchain.wallet_id.xrp'),
//                '1'
//            ),
//            'bch' => new Coin(
//                'bch',
//                config('blockchain.wallet_id.bch'),
//                '100000000'
//            ),
//            'eos' => new Coin(
//                'eos',
//                config('blockchain.wallet_id.eos'),
//                '1'
//            ),
//            'ada' => new Coin(
//                'ada',
//                config('blockchain.wallet_id.ada'),
//                '1000000'
//            ),
//            'ltc' => new Coin(
//                'ltc',
//                config('blockchain.wallet_id.ltc'),
//                '100000000'
//            ),
//            'eos.EOS' => new Coin(
//                'eos',
//                config('blockchain.wallet_id.eos'),
//                '1'
//            ),
//        ];
    }

    static function getCoinConfig($coin, $networkId = null)
    {
//        $coins = self::getCoins();
//
//        if (!array_key_exists($coin, $coins)) {
            return self::getConfigDB($coin, $networkId);
//        }

//        return $coins[$coin];
    }

    public static function getConfigDB($coin, $networkId = null)
    {
        $coinDB = DB::table('coins', 'c')
            ->join('network_coins as nc', 'c.id', 'nc.coin_id')
            ->join('networks as n', 'nc.network_id', 'n.id')
            ->where([
                'c.coin' => $coin,
                'c.env' => config('blockchain.network'),
                'n.id' => $networkId,
                'n.enable' => true,
                'n.network_deposit_enable' => true,
                'nc.network_enable' => true,
                'nc.network_deposit_enable' => true,
            ])
            ->when($networkId, function ($query) use ($networkId) {
                $query->where('n.id', $networkId);
            })
            ->selectRaw('nc.decimal, c.type, nc.contract_address, nc.network_id, n.symbol as network_symbol, n.network_code')
            ->first();

        if (empty($coinDB)) {
            return null;
        }

        return new Coin($coin, null, pow(10, $coinDB->decimal), $coinDB->type, $coinDB->contract_address, $coinDB->network_id, $coinDB->network_symbol, $coinDB->network_code);
    }
}
