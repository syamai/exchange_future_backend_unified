<?php

/**
 * Created by PhpStorm.
 * Date: 8/23/2016
 * Time: 4:08 PM
 */

namespace App\Facades;

use App\Consts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FormatFun
{

    public function formatNameFile($name)
    {
        $arrayElement = explode('.', $name);
        $type = array_pop($arrayElement);
        return Str::random(8) . uniqid() . '.' . $type;
    }

    public function reFileName($file)
    {
        $fileName = $file->getClientOriginalName();
        $arrayName = explode('.', $fileName);
        $tail = array_pop($arrayName);
        return Str::random(8) . uniqid()  . '.' . $tail;
    }

    public function formatAppName($name)
    {
        return ucfirst(Str::camel(Str::plural($name)));
    }

    public function formatCoin($coin)
    {
        $coinParams = explode('.', $coin);
        if (count($coinParams) > 1 && $coinParams[0] != "") {
            $network = DB::table('networks')
                ->where([
                    'symbol' => $coinParams[0]
                ])
                ->first();

            if ($network) {
                $currencyDB = DB::table('coins', 'c')
                    ->join('network_coins as nc', 'c.id', 'nc.coin_id')
                    ->where('nc.contract_address', $coinParams[1])
                    ->where('c.env', config('blockchain.network'))
                    ->value('c.coin');

                if ($currencyDB) {
                    return $currencyDB;
                }
            }
        }


//        if ($coin === Consts::UPDATED_CURRENCY_EOS) {
//            return Consts::CURRENCY_EOS;
//        }

        $coin = strtolower($coin);

        return $coin;
    }

    public function formatCoinNetwork($coin)
    {
        if ($coin == 'usdt') {
            $network = DB::table('networks')
                ->where([
                    'symbol' => 'erc20'
                ])
                ->first();
            if ($network) {
                $currencyDB = DB::table('coins', 'c')
                    ->join('network_coins as nc', 'c.id', 'nc.coin_id')
                    ->where('c.coin', $coin)
                    ->where('c.env', config('blockchain.network'))
                    ->select(['c.coin', 'nc.network_id'])
                    ->first();

                if ($currencyDB) {
                    return $currencyDB;
                }
            }
        } else {
            $coinParams = explode('.', $coin);
            if (count($coinParams) > 1 && $coinParams[0] != "") {
                $network = DB::table('networks')
                    ->where([
                        'symbol' => $coinParams[0]
                    ])
                    ->first();

                if ($network) {
                    $currencyDB = DB::table('coins', 'c')
                        ->join('network_coins as nc', 'c.id', 'nc.coin_id')
                        ->where('nc.contract_address', $coinParams[1])
                        ->where('c.env', config('blockchain.network'))
                        ->select(['c.coin', 'nc.network_id'])
                        ->first();

                    if ($currencyDB) {
                        return $currencyDB;
                    }
                }
            }
        }


        $coin = strtolower($coin);
        $currencyDB = DB::table('coins', 'c')
            ->join('network_coins as nc', 'c.id', 'nc.coin_id')
            ->where('nc.contract_address', $coin)
            ->select(['c.coin', 'nc.network_id'])
            ->first();
        if ($currencyDB) {
            return $currencyDB;
        }

        return (object) [
            'coin' => $coin,
            'network_id' => null
        ];
    }

    public function getPlatformCurrency($coin)
    {
        $type = DB::table('coins')
            ->where(compact('coin'))
            ->where('env', config('blockchain.network'))
            ->value('type');

        if ($type === Consts::CURRENCY_ETH  || $type === Consts::ETH_TOKEN) {
            return Consts::CURRENCY_ETH;
        }

        if ($type === Consts::CURRENCY_BNB  || $type === Consts::BNB_TOKEN) {
            return Consts::CURRENCY_BNB;
        }

        return $coin;
    }

    public function formatCoinWebHook($coin)
    {
        /*if (strtolower($coin) === strtolower(Consts::UPDATED_CURRENCY_EOS)) {
            return Consts::CURRENCY_EOS;
        }*/

        return $this->formatCoinNetwork($coin);
    }
}
