<?php

/**
 * Created by PhpStorm.
 * User: diamond
 * Date: 1/21/19
 * Time: 3:49 PM
 */

namespace App\Facades;

use App\Http\Services\Blockchain\CoinConfigs;
use App\Models\CoinsConfirmation;
use Illuminate\Support\Facades\DB;

class CheckFun
{
    public function withdraw($coin)
    {
        return CoinsConfirmation::where([
            'coin' => $coin,
            'is_withdraw' => 1
        ])->count();
    }

    public function deposit($coin, $networkId)
    {
        /*return CoinsConfirmation::where([
            'coin' => $coin,
            'is_deposit' => 1
        ])->count();*/

        return $networkCoins = DB::table('coins', 'c')
            ->join('network_coins as nc', 'c.id', 'nc.coin_id')
            ->join('networks as n', 'nc.network_id', 'n.id')
            ->where([
                'c.coin' => $coin,
                'n.id' => $networkId,
                'n.enable' => true,
                'nc.network_enable' => true,
                'n.network_deposit_enable' => true,
                'nc.network_deposit_enable' => true,

            ])
            ->count();
    }

    public function withdrawAndDeposit($coin)
    {
        return CoinsConfirmation::where([
            'coin' => $coin,
            'is_deposit' => 1,
            'is_withdraw' => 1
        ])->count();
    }

    public function blockchainAddress($currency, $address, $networkId = null)
    {
        $domain = config('blockchain.api_wallet');
        $port = config('blockchain.port_wallet');
        $apiKey = config('blockchain.x_api_key_wallet');

        $coin = CoinConfigs::getCoinConfig($currency, $networkId);
        if (!$coin) {
            return false;
        }
        $currencyPlatform = $coin->getCurrentTx();

        $url = "{$domain}:{$port}/api/{$currencyPlatform}/address/{$address}/validate";

        $client = new \GuzzleHttp\Client(['verify' => false]);

        try {
            $res = $client->request('GET', $url, [
                'connect_timeout' => 10000,
                'headers' => [
                    "x-api-key" => $apiKey,
                ],
            ]);

            $data = $res->getBody()->getContents();
            $data = json_decode($data, true);

            if ($data['isValid'] === true) {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            return false;
        }
    }
}
