<?php
namespace App\Http\Services;

use App\Consts;
use Illuminate\Support\Facades\DB;
use App\Models\AirdropSetting;
use App\Models\UserSetting;
use Illuminate\Support\Facades\Log;
use App\Http\Services\UserSettingService;
use App\Utils\BigNumber;
use Exception;

class PayFeeByAmalService
{
    private $userSettingService;
    public function __construct()
    {
        $this->userSettingService = new UserSettingService();
    }
    public function checkEnableFeeAmal($userId)
    {
        $enable = $this->checkEnablePayFeeAmalAdmin() && $this->checkEnablePayFeeAmalUser($userId);
        logger('tradinglog checkEnableFeeAmal'.$enable);
        return $enable;
    }
    public function checkEnablePayFeeAmalAdmin()
    {
        $enable = false;
        $setting = AirdropSetting::first();
        if ($setting) {
            $enable = $setting->enable_fee_amal;
        }
        return $enable;
    }
    public function checkEnablePayFeeAmalUser($userId)
    {
        $enable = $this->userSettingService->getValueFromKey('amal_pay', $userId);
        if ($enable == 1) {
            return true;
        }
        return false;
    }

    public function payAmalFeeFollowTypeWallet($userId, $amalFeeDiscounted, $nameWallet = null, $connection = null)
    {
        logger('tradinglog payAmalFeeFollowTypeWallet 1'.$amalFeeDiscounted);
        if (is_null($nameWallet)) {
            $wallet = $this->userSettingService->getValueFromKey('amal_pay_wallet', $userId);
        } else {
            $wallet = $nameWallet;
        }
        if (!$wallet) {
            return false;
        }
        $tableFee = $this->getPayFeeAmalTableName($wallet);
        $amalBalanceRecord = $this->getRecord($tableFee, $userId, $wallet, $connection);
        logger('tradinglog payAmalFeeFollowTypeWallet 2'.$tableFee);
        if (!$amalBalanceRecord) {
            return false;
        }
        $balance = $this->getBalance($amalBalanceRecord, $wallet);
        logger('tradinglog payAmalFeeFollowTypeWallet 3');
        if (!$this->checkAvailablePayFee($balance, $amalFeeDiscounted, $wallet)) {
            return false;
        }
        $balanceAfterPayFee = $this->getBalanceAfterPayFee($balance, $amalFeeDiscounted, $wallet);
        $rs = $this->excutedPayFee($userId, $tableFee, $wallet, $balanceAfterPayFee, $connection);
        logger('tradinglog payAmalFeeFollowTypeWallet 4'.$rs);
        return $rs;
    }

    public function getPayFeeAmalTableName($name)
    {

        //table mam


        //table margin
        if ($name == Consts::TYPE_MARGIN_BALANCE) {
            return "amal_margin_accounts";
        }


        //other table
        if ($name == Consts::PERPETUAL_DIVIDEND_BALANCE || $name == Consts::DIVIDEND_BALANCE) {
            $name = Consts::TYPE_AIRDROP_BALANCE;
        }

        $prefix = "";
        if ($name != Consts::TYPE_MAIN_BALANCE) {
            $prefix = $name . "_";
        }
        return $prefix . 'amal_accounts';
    }

    public function getBalance($record, $wallet)
    {
        $balance = [];
        if ($wallet == Consts::PERPETUAL_DIVIDEND_BALANCE) {
            $balance = [
                "balance" => $record->balance_bonus,
                "available_balance" => $record->available_balance_bonus
            ];
        } else {
            $balance = [
                "balance" => $record->balance,
                "available_balance" => $record->available_balance
            ];
        }
        return $balance;
    }

    public function getRecord($tableFee, $userId, $wallet, $connection)
    {
        $query = $connection ? DB::connection($connection)->table($tableFee) : DB::table($tableFee);

        if ($wallet == Consts::TYPE_MARGIN_BALANCE) {
            $accountId = DB::table($tableFee)->where('owner_id', $userId)->first()->id;
            return $query->where('id', $accountId)
                ->lockForUpdate()
                ->first();
        }

        return $query->where('id', $userId)
            ->lockForUpdate()
            ->first();
    }
    public function checkAvailablePayFee($balance, $amalFeeDiscounted, $wallet)
    {
        $amalAvailableBalance = $balance['available_balance'];
        if ($wallet == Consts::PERPETUAL_DIVIDEND_BALANCE) {
            $amalAvailableBalance = BigNumber::new($balance['balance'])->sub($balance['available_balance'])->toString();
            ;
        }
        $isEnoughAmal = BigNumber::new($amalAvailableBalance)->comp($amalFeeDiscounted);
        if ($isEnoughAmal < 0) {
            logger("tradinglog checkAvailablePayFee khong du tien");
            return false; // Break - Nothing to do
        }
        logger("tradinglog checkAvailablePayFee du tien");
        return true;
    }
    public function getBalanceAfterPayFee($balance, $amalFeeDiscounted, $wallet)
    {
        $amalAvailableBalance = $balance['available_balance'];
        $amalBalance = $balance['balance'];
        if ($wallet == Consts::PERPETUAL_DIVIDEND_BALANCE) {
            $balance['balance'] = BigNumber::new($amalBalance)->sub($amalFeeDiscounted)->toString();
        } else {
            $balance['available_balance'] = BigNumber::new($amalAvailableBalance)->sub($amalFeeDiscounted)->toString();
            $balance['balance'] = BigNumber::new($amalBalance)->sub($amalFeeDiscounted)->toString();
        }
        return $balance;
    }
    private function excutedPayFee($userId, $tableFee, $wallet, $balanceAfterPayFee, $connection)
    {
        $amalAvailableBalance = $balanceAfterPayFee['available_balance'];
        $amalBalance = $balanceAfterPayFee['balance'];

        try {
            $query = $connection ? DB::connection($connection)->table($tableFee) : DB::table($tableFee);

            if ($wallet == Consts::PERPETUAL_DIVIDEND_BALANCE) {
                $query->where('id', $userId)
                    ->update([
                        'balance_bonus' => $amalBalance,
                        // 'available_balance_bonus' => $amalAvailableBalance,
                    ]);
            } elseif ($wallet == Consts::TYPE_MARGIN_BALANCE) {
                $amalAccountId = DB::table($tableFee)->where('owner_id', $userId)->first()->id;
                $query->where('id', $amalAccountId)
                    ->update([
                        'balance' => $amalBalance,
                        'available_balance' => $amalAvailableBalance,
                    ]);
            } else {
                $query->where('id', $userId)
                    ->update([
                        'balance' => $amalBalance,
                        'available_balance' => $amalAvailableBalance,
                    ]);
            }
            return $wallet;
        } catch (Exception $e) {
            logger()->error($e->getMessage());
            return false;
        }
    }
}
