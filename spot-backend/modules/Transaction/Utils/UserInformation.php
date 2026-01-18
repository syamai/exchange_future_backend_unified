<?php
/**
 * Created by PhpStorm.

 * Date: 6/23/19
 * Time: 1:40 PM
 */

namespace Transaction\Utils;

use App\Http\Services\Blockchain\CoinConfigs;
use Illuminate\Support\Facades\DB;

class UserInformation
{
    public static function userDepositByTransaction($transaction, $select = '*')
    {
        $currency = $transaction->currency;
        $networkId = $transaction->network_id;
        $filter = self::buildFilter($transaction, $currency, $networkId);

        $query = DB::table("user_blockchain_addresses")
            ->where($filter);

        if ($select === '*') {
            return $query->get();
        }

        return $query->value($select);
    }

    public static function buildFilter($transaction, $currency, $network_Id)
    {
        $coin = CoinConfigs::getCoinConfig($currency, $network_Id);

        if ($coin->isTagAttached()) {
            $blockchain_sub_address = $transaction->blockchain_sub_address;
            $filter = compact('blockchain_sub_address', 'network_Id', 'currency');
        } else {
            $blockchain_address = $transaction->to_address;
            $filter = compact('blockchain_address', 'network_Id', 'currency');
        }

        return $filter;
    }

    public static function getUserIdDepositByTransaction($transaction)
    {
        return self::userDepositByTransaction($transaction, 'user_id');
    }
}
