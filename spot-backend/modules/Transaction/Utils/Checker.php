<?php
/**
 * Created by PhpStorm.
 * Date: 5/25/19
 * Time: 9:39 AM
 */

namespace Transaction\Utils;

use App\Facades\FormatFa;
use Transaction\Http\Services\WalletService;

class Checker
{
    public static function isInternalTransaction($currency, $to_address, $networkId)
    {
        if (self::getTypeTransaction($currency, $to_address, $networkId) === 0) {
            return true;
        }
        return false;
    }

    public function getIsExternalTransaction($currency, $to_address, $networkId)
    {
        if (self::getTypeTransaction($currency, $to_address, $networkId) === 1) {
            return true;
        }
        return false;
    }

    public static function getTypeTransaction($currency, $to_address, $networkId)
    {
        //$currency = FormatFa::getPlatformCurrency($currency);

        $currencyAddress = app(WalletService::class)->getUserIdByAddress($currency, $to_address, $networkId);

        if ($currencyAddress) {
            return 0;
        }

        return 1;
    }
}
