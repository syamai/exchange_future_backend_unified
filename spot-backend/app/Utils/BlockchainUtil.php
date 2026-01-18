<?php

namespace App\Utils;

use App\Consts;

class BlockchainUtil
{
    public static function getTransactionUrl($currency, $transactionId)
    {
        switch ($currency) {
            case Consts::CURRENCY_BTC:
                return "https://blockchain.info/tx/{$transactionId}";
            case Consts::CURRENCY_ETH:
                return "https://gastracker.io/tx/{$transactionId}";
            case Consts::CURRENCY_BCH:
                return "https://explorer.bitcoin.com/bch/tx/{$transactionId}";
            case Consts::CURRENCY_XRP:
                return "https://xrpcharts.ripple.com/#/transactions/{$transactionId}";
            case Consts::CURRENCY_LTC:
                return "https://live.blockcypher.com/ltc/tx/{$transactionId}";
            default:
                return '';
        }
    }
}
