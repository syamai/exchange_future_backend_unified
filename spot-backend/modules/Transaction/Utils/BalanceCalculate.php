<?php
/**
 * Created by PhpStorm.

 * Date: 5/29/19
 * Time: 2:21 PM
 */

namespace Transaction\Utils;

use App\Utils\BigNumber;

class BalanceCalculate
{
    public static function approvedWalletWithdraw($transaction)
    {
        return BigNumber::new(abs($transaction->amount))->toString();
    }

    public static function approvedWithdraw($transaction)
    {
        return BigNumber::new($transaction->amount)->sub($transaction->fee)->toString();
    }

    public static function jobWithdraw($transaction)
    {
        return (float) BigNumber::new(-1)->mul($transaction->amount)->sub($transaction->fee)->toString();
    }

    public static function rejectWithdraw($transaction)
    {
        return BigNumber::new(-1)->mul($transaction->amount)->sub($transaction->fee)->toString();
    }

    public static function internalCreateDeposit($transaction)
    {
        return BigNumber::new(-1)->mul($transaction->amount)->toString();
    }
}
