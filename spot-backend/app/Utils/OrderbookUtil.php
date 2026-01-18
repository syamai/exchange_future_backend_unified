<?php

namespace App\Utils;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;
use App\Consts;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\DB;

class OrderbookUtil
{
    const TIME_RANGE = 1200000; // 20 minutes

    public static function getPriceGroup($tradeType, $price, $ticker)
    {
        $basePrice = $tradeType == Consts::ORDER_TRADE_TYPE_BUY ? floor(BigNumber::new($price)->div($ticker)->toString()) : ceil(BigNumber::new($price)->div($ticker)->toString());
        return BigNumber::new($basePrice)->mul($ticker)->toString();
    }

    public static function getPriceRange($tradeType, $price, $ticker)
    {
        if ($tradeType == Consts::ORDER_TRADE_TYPE_BUY) {
            $min = OrderbookUtil::getPriceGroup($tradeType, $price, $ticker);
            return [
                'min' => $min,
                'max' => BigNumber::new($min)->add($ticker)->toString()
            ];
        } else {
            $max = OrderbookUtil::getPriceGroup($tradeType, $price, $ticker);
            return [
                'min' => BigNumber::new($max)->sub($ticker)->toString(),
                'max' => $max
            ];
        }
    }
}
