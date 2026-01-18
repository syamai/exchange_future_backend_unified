<?php
/**
 * Created by PhpStorm.
 * Date: 6/19/19
 * Time: 1:40 PM
 */

namespace Transaction\Http\Services;

use App\Consts;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\DB;

class UserBalanceService
{
    public function getBalanceTransactionMains($currencies, $userId)
    {
        $result = [];

        foreach ($currencies as $currency) {
            $result[$currency] = $this->getBalanceTransactionMain($currency, $userId);
        }

        return $result;
    }

    public function getBalanceTransactionMain($currency, $userId)
    {
        $mainAccount = $this->getMainAccountUser($currency, $userId);
        $marginTotalBalance = 0;
        $mamTotalBalance = 0;

        /*if ($currency === Consts::CURRENCY_BTC || $currency === Consts::CURRENCY_AMAL) {
            $marginAccount = $this->getMarginAccountUser($currency, $userId);
            $marginTotalBalance = $marginAccount->balance;
            $mamAccount = $this->getMamAccountUser($currency, $userId);
            $mamTotalBalance = $mamAccount->balance;
        }*/

        $spotAccount = $this->getSpotAccountUser($currency, $userId);

        $mainTotalBalance = $mainAccount->balance;
        $spotTotalBalance = $spotAccount->balance;

        $balance = $this->getBalance($mainTotalBalance, $marginTotalBalance, $spotTotalBalance, $mamTotalBalance);
        $available_balance = $mainAccount->available_balance;
        $isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
        if ($isSpotMainBalance) {
            $available_balance = $spotAccount->available_balance;
        }

        $in_order = $this->getInOrder($balance, $available_balance);

        if ($currency === Consts::CURRENCY_AMAL) {
            $airdropAccount = $this->getAirdropAccountUser($userId);
            $balance = BigNumber::new($balance)
                ->add($airdropAccount->balance)
                ->add($airdropAccount->balance_bonus)
                ->toString();
        }

        $data = compact('balance', 'available_balance', 'in_order', 'currency');

        return $this->formatData($data, $mainAccount, $currency);
    }

    public function formatData($data, $mainAccount, $currency)
    {
        $data['usd_amount'] = $mainAccount->usd_amount;
        $data['id'] = $mainAccount->id;

        if ($currency != Consts::CURRENCY_USD) {
            $data['blockchain_address'] = $mainAccount->blockchain_address;
        }

        if ((Consts::CURRENCY_XRP == $currency || Consts::CURRENCY_EOS == $currency)) {
            $data['blockchain_sub_address'] = $mainAccount->blockchain_sub_address;
        }

        return $data;
    }

    public function getBalance($mainTotalBalance, $marginTotalBalance, $spotTotalBalance, $mamTotalBalance)
    {
        return BigNumber::new($mainTotalBalance)
            ->add($marginTotalBalance)
            ->add($spotTotalBalance)
            ->add($mamTotalBalance)
            ->toString();
    }

    public function getInOrder($balance, $available_balance)
    {
        return BigNumber::new($balance)->sub($available_balance)->toString();
    }

    public function getMainAccountUser($currency, $userId)
    {
        return DB::table("{$currency}_accounts")->find($userId);
    }

    public function getAirdropAccountUser($userId)
    {
        return DB::table("airdrop_amal_accounts")->find($userId);
    }

    public function getMarginAccountUser($currency, $userId)
    {
        if ($currency == Consts::CURRENCY_AMAL) {
            return DB::table("amal_margin_accounts")->where('owner_id', $userId)->first();
        }
        return DB::table("margin_accounts")->where('owner_id', $userId)->first();
    }

    public function getSpotAccountUser($currency, $userId)
    {
        return DB::table("spot_{$currency}_accounts")->find($userId);
    }

    public function getDecimalCoin($currency)
    {
        $currency = strtolower($currency);
        $coin = DB::table("coins")->where("coin", $currency)->first();
        if ($coin) {
            if ($coin->coin == Consts::CURRENCY_XRP || $coin->coin == Consts::CURRENCY_EOS) {
                return Consts::EOS_XRP_DECIMAL;
            }
            return $coin->decimal;
        }
        return null;
    }
}
