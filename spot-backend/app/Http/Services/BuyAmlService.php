<?php
/**
 * Created by PhpStorm.
 * Date: 5/3/19
 * Time: 3:16 PM
 */

namespace App\Http\Services;

use App\Consts;
use App\Events\AmlSettingUpdated;
use App\Events\AmlBalanceUpdated;
use App\Events\MainBalanceUpdated;
use App\Facades\AmalCalculatorFacade;
use App\Models\AmalSetting;
use App\Models\AmalCashBack;
use App\Utils\BigNumber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BuyAmlService
{
    public function buy($input): array
    {
        $input = escapse_string_params($input);
        $amlSetting = $this->getAmlSetting();
        $user_id = auth()->id();
        $amount = $input['amount'];
        $totalEstimate = $input['total_amount'];
        $amalPrice = $input['amal_price'];
        $paymentEstimate = $input['payment'];
        $currency = strtolower($input['currency']);
        $accountTable = "{$currency}_accounts";

        $account = $this->getAccount($accountTable, $user_id);

        $priceKey = "{$currency}_price"; // usd_price - btc_price - eth_price - usdt_price
        $price = $amlSetting->$priceKey;

        $referredBonus = AmalCalculatorFacade::getReferredBonus($user_id, $amount);
        $levelBonus = AmalCalculatorFacade::getLevelBonus($user_id, $amount);
        $bonus = AmalCalculatorFacade::getTotalBonus($referredBonus, $levelBonus);
        $payment = AmalCalculatorFacade::getPayment($price, $amount);
        $total = AmalCalculatorFacade::getTotal($amount, $bonus);

        $referrerUserId = AmalCalculatorFacade::getReferrerId($user_id);
        $commision = $this->getCommision($referrerUserId, $user_id, $currency, $payment);

        $this->validTransaction($amlSetting, $account, $payment, $paymentEstimate, $total, $totalEstimate, $price, $amalPrice)
            ->updateAmlSetting($amlSetting, $currency, $total, $payment)
            ->updateCurrencyAccount($accountTable, $payment, $user_id)
            ->updateAmlAccount($user_id, $total)
            ->updateReffererAcount($accountTable, $referrerUserId, $commision);

        $this->triggerEvent($accountTable, $currency, $user_id, $referrerUserId);

        $price_bonus = 0;
        return array_merge(compact('user_id', 'amount', 'currency', 'price', 'payment', 'total', 'bonus', 'price_bonus'));
    }

    private function getCommision($referrerUserId, $user_id, $currency, $payment): int|string
    {
        $commision = 0;
        $referrerCommisionPercent = AmalSetting::first()->referrer_commision_percent;
        if ($referrerUserId != null && $referrerCommisionPercent != null) {
            $commision = BigNumber::new($payment)->mul($referrerCommisionPercent)->div(100)->toString();
            if ($currency == 'usd') {
                $commision = BigNumber::round($commision, BigNumber::ROUND_MODE_FLOOR, Consts::DIGITS_NUMBER_PRECISION_2);
            } else {
                $commision = BigNumber::round($commision, BigNumber::ROUND_MODE_FLOOR, Consts::DIGITS_NUMBER_PRECISION);
            }

            if ($commision != 0) {
                $cashBackRecord = new AmalCashBack;
                $cashBackRecord->user_id = $referrerUserId;
                $cashBackRecord->referred_user_id = $user_id;

                $referrer_email = User::where('id', $referrerUserId)->value('email');
                $cashBackRecord->referrer_email = $referrer_email;

                $referred_email = User::where('id', $user_id)->value('email');
                $cashBackRecord->referred_email = $referred_email;

                $cashBackRecord->currency = $currency;
                $cashBackRecord->rate = $referrerCommisionPercent;
                $cashBackRecord->bonus = $commision;
                $cashBackRecord->save();
            }
        }
        return $commision;
    }

    private function triggerEvent($accountTable, $currency, $user_id, $referrerUserId): void
    {
        $currency_balance = DB::table($accountTable)->where('id', $user_id)->first();
        event(new MainBalanceUpdated($user_id, ['total_balance' => $currency_balance->balance, 'available_balance' => $currency_balance->available_balance, 'coin_used' => $currency]));

        if ($referrerUserId != null) {
            $currency_balance = DB::table($accountTable)->where('id', $referrerUserId)->first();
            event(new MainBalanceUpdated($referrerUserId, ['total_balance' => $currency_balance->balance, 'available_balance' => $currency_balance->available_balance, 'coin_used' => $currency]));
        }

        $amal_balance = DB::table('amal_accounts')->where('id', $user_id)->first();
        event(new AmlBalanceUpdated($user_id, ['total_balance' => $amal_balance->balance, 'available_balance' => $amal_balance->available_balance]));
    }

    private function validTransaction($amlSetting, $account, $payment, $paymentEstimate, $total, $totalEstimate, $price, $amalPrice): static
    {
        if (0 !== BigNumber::new($price)->comp($amalPrice)) {
            throw new HttpException(422, __('exception.amal_price_change'));
        }
        if (0 !== BigNumber::new($total)->comp($totalEstimate)) {
            throw new HttpException(422, __('exception.amal_price_change'));
        }
        if (BigNumber::new($payment)->comp($paymentEstimate) > 0) {
            throw new HttpException(422, __('exception.amal_price_change'));
        }
        if ($amlSetting->amount <= 0) {
            throw new HttpException(422, __('exception.amal_not_enough'));
        }
        if ($amlSetting->amount < $total) {
            throw new HttpException(422, __('exception.amal_remaining_not_enough'));
        }
        if ($account->available_balance < $payment) {
            throw new HttpException(422, __('exception.amal_payment_not_enough'));
        }
        return $this;
    }

    private function getAmlSetting()
    {
        return AmalSetting::lockForUpdate()->first();
    }

    private function updateAmlSetting($amlSetting, $currency, $total, $money): static
    {
        $amountKey = "{$currency}_sold_amount";
        $moneyKey = "{$currency}_sold_money";

        $data['amount'] = DB::raw("amount - " . $total);
        $data[$amountKey] = DB::raw("{$amountKey} + " . $total);
        $data[$moneyKey] = DB::raw("{$moneyKey} + " . $money);

        $amlSetting->update($data);
        event(new AmlSettingUpdated($amlSetting));
        return $this;
    }

    private function getAccount($table, $user_id)
    {
        return DB::table($table)->where('id', $user_id)->lockForUpdate()->first();
    }

    private function updateCurrencyAccount($table, $payment, $user_id): static
    {
        $data['balance'] = DB::raw('balance - ' . $payment);
        $data['available_balance'] = DB::raw('available_balance - ' . $payment);
        DB::table($table)->where('id', $user_id)->update($data);
        return $this;
    }

    private function updateAmlAccount($user_id, $amount): static
    {
        $data['balance'] = DB::raw('balance + ' . $amount);
        $data['available_balance'] = DB::raw('available_balance + ' . $amount);
        DB::table('amal_accounts')->where('id', $user_id)->update($data);
        return $this;
    }

    private function updateReffererAcount($table, $user_id, $commision): static
    {
        if ($user_id == null || $commision == 0) {
            return $this;
        }
        DB::table($table)->where('id', $user_id)->lockForUpdate()->first();
        $data['balance'] = DB::raw('balance + ' . $commision);
        $data['available_balance'] = DB::raw('available_balance + ' . $commision);
        DB::table($table)->where('id', $user_id)->update($data);
        return $this;
    }
}
