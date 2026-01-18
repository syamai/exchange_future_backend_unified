<?php
/**
 * Created by PhpStorm.
 * Date: 4/24/19
 * Time: 10:40 AM
 */

namespace App\Facades;

use App\Consts;
use App\Models\AmalSetting;
use App\Models\CoinMarketCapTicker;
use App\Models\User;
use App\Utils\BigNumber;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AmalCalculator
{
    public function getPayment($price, $amount)
    {
        return BigNumber::new($price)->mul($amount)->toString();
    }

    public function getReferredBonus($user_id, $amount)
    {
        $referrerUserId = $this->getReferrerId($user_id);
        $bonusPercent = AmalSetting::first()->referred_bonus_percent;
        if ($referrerUserId == null || $bonusPercent == null) {
            return 0;
        } else {
            return BigNumber::new($amount)->mul($bonusPercent)->div(100)->toString();
        }
    }

    public function getLevelBonus($user_id, $amount)
    {
        $bonusLevel1 = AmalSetting::first()->amal_bonus_1;
        $bonusLevel2 = AmalSetting::first()->amal_bonus_2;
        if ($bonusLevel2 != null && $amount >= $bonusLevel2) {
            $bonusPercent = AmalSetting::value('percent_bonus_2');
            return BigNumber::new($amount)->mul($bonusPercent)->div(100)->toString();
        } elseif ($bonusLevel1 != null && $amount >= $bonusLevel1) {
            $bonusPercent = AmalSetting::value('percent_bonus_1');
            return BigNumber::new($amount)->mul($bonusPercent)->div(100)->toString();
        } else {
            return 0;
        }
    }

    public function getTotalBonus($referredBonus, $levelBonus)
    {
        return BigNumber::new($referredBonus)->add($levelBonus)->toString();
    }

    public function getTotal($amount, $bonus)
    {
        return BigNumber::new($amount)->add($bonus)->toString();
    }

    public function getReferrerId($user_id)
    {
        return app(User::class)->getReferrerId($user_id);
    }
}
