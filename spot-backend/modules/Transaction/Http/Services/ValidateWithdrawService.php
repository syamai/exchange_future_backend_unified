<?php
/**
 * Created by PhpStorm.
 * Date: 5/21/19
 * Time: 10:20 AM
 */

namespace Transaction\Http\Services;

use App\Consts;
use App\Http\Services\EnableWithdrawalSettingService;
use App\Http\Services\PriceService;
use App\Models\UserSecuritySetting;
use App\Utils\BigNumber;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class ValidateWithdrawService
 * @package Transaction\Http\Services
 */
class ValidateWithdrawService
{

    private $walletService;

    private $transactionService;

    public function __construct(WalletService $walletService, TransactionService $transactionService)
    {
        $this->walletService = $walletService;
        $this->transactionService = $transactionService;
    }

    /**
     * @param $user
     * @param $params
     * @param $withdrawLimit
     * @throws \Exception
     */
    public function validator($user, $params, $withdrawLimit)
    {
        $this->validateWithdrawInput($user, $params, $withdrawLimit)
            ->validateUserSecurity($user->id)
            ->validateUserWithdrawalSetting($user, $params);
    }

    private function validateUserWithdrawalSetting($user, $params)
    {
        $service = new EnableWithdrawalSettingService();
        $currency = $params['currency'];
        $enableWithdrawal = $service->checkBlockWithdrawal($currency, $user->email);

        if ($enableWithdrawal) {
            throw new HttpException(406, Consts::WITHDRAW_IS_BLOCKING);
        }

        return $this;
    }

    /**
     * @param $user
     * @param $params
     * @param $withdrawLimit
     * @return $this
     */
    private function validateWithdrawInput($user, $params, $withdrawLimit)
    {
        $amount = $params['amount'];
        $currency = $params['currency'];

        $userBalance = $this->walletService->getUserBalance($currency, $user->id, true);

        $this->checkAmount($amount)
            ->checkFeeWithdrawal($params, $withdrawLimit)
            ->checkLimitWithdrawal($params, $withdrawLimit)
            ->checkDailyLimitWithdrawal($params, $withdrawLimit)
            ->checkMinWithdrawal($params, $withdrawLimit)
            ->checkWhiteListAddress($user, $params)
            ->checkMiniumWithdraw($amount, $withdrawLimit)
            ->checkUserBalance($userBalance, $amount, $withdrawLimit)
            ->checkWithdrawInDay($user->id, $currency, $amount, $withdrawLimit)
            ->checkWithdrawBigAmount($user, $params, $currency, $amount);

        return $this;
    }

    private function checkFeeWithdrawal($params, $withdrawLimit)
    {
        if (BigNumber::new(-1)->mul($params['fee'])->add($withdrawLimit->fee)->toString() != 0) {
            throw ValidationException::withMessages([
                'fee' => [Consts::WITHDRAW_ERROR_FEE_WITHDRAW],
            ]);
        }
        return $this;
    }

    private function checkLimitWithdrawal($params, $withdrawLimit)
    {
        if (BigNumber::new(-1)->mul($params['limit'])->add($withdrawLimit->limit)->toString() != 0) {
            throw ValidationException::withMessages([
                'limit' => [Consts::WITHDRAW_ERROR_LIMIT_WITHDRAW],
            ]);
        }
        return $this;
    }

    private function checkDailyLimitWithdrawal($params, $withdrawLimit)
    {
        if (BigNumber::new(-1)->mul($params['daily_limit'])->add($withdrawLimit->daily_limit)->toString() != 0) {
            throw ValidationException::withMessages([
                'daily_limit' => [Consts::WITHDRAW_ERROR_DAILY_LIMIT_WITHDRAW],
            ]);
        }
        return $this;
    }

    private function checkMinWithdrawal($params, $withdrawLimit)
    {
        if (BigNumber::new(-1)->mul($params['minium_withdrawal'])->add($withdrawLimit->minium_withdrawal)->toString() != 0) {
            throw ValidationException::withMessages([
                'minium_withdrawal' => [Consts::WITHDRAW_ERROR_MINIMUM_WITHDRAW],
            ]);
        }
        return $this;
    }

    /**
     * @param $amount
     * @return $this
     */
    private function checkAmount($amount)
    {
        // transaction amount is negative
        if (!BigNumber::new($amount)->isNegative() && BigNumber::new($amount)->comp(0) !== 0) {
            throw ValidationException::withMessages([
                'amount' => [Consts::WITHDRAW_ERROR_AMOUNT_WITHDRAW_IS_POSITIVE],
            ]);
        }
        return $this;
    }

    /**
     * @param $user
     * @param $params
     * @return $this
     */
    private function checkWhiteListAddress($user, $params)
    {
        if ($user->isEnableWhiteList() && !$user->checkWhiteListAddress(\Arr::get($params, 'blockchain_address'))) {
            throw ValidationException::withMessages([
                'blockchain_address' => [Consts::WITHDRAW_ERROR_WHITELIST_ADDRESS],
            ]);
        }
        return $this;
    }

    /**
     * @param $amount
     * @param $withdrawLimit
     * @return $this
     */
    private function checkMiniumWithdraw($amount, $withdrawLimit)
    {
        // Check minium_withdrawal
        if ((BigNumber::new(-1)->mul($amount)->comp($withdrawLimit->fee) < 0) &&
            BigNumber::new(-1)->mul($amount)->comp($withdrawLimit->minium_withdrawal) >= 0) {
            throw ValidationException::withMessages([
                'amount' => ['min_you_get'],
            ]);
        }
        if (BigNumber::new(-1)->mul($amount)->comp($withdrawLimit->minium_withdrawal) < 0) {
            throw ValidationException::withMessages([
                'amount' => [Consts::WITHDRAW_ERROR_MINIMUM_WITHDRAW],
            ]);
        }
        return $this;
    }

    /**
     * @param $userBalance
     * @param $amount
     * @param $withdrawLimit
     * @return $this
     */
    private function checkUserBalance($userBalance, $amount, $withdrawLimit)
    {
        if (!$this->transactionService->isUserBalanceEnough($userBalance, $amount, $withdrawLimit)) {
            throw ValidationException::withMessages([
                'amount' => [Consts::WITHDRAW_ERROR_NOT_ENOUGH_BALANCE],
            ]);
        }
        return $this;
    }

    /**
     * @param $user_id
     * @param $currency
     * @param $amount
     * @param $withdrawLimit
     * @return $this
     */
    private function checkWithdrawInDay($user_id, $currency, $amount, $withdrawLimit)
    {
        // Check total withdraw in day
        if ($this->transactionService->isWithdrawOverLimit($user_id, $currency, $amount, $withdrawLimit)) {
            throw ValidationException::withMessages([
                'amount' => [Consts::WITHDRAW_ERROR_OVER_LIMIT],
            ]);
        }
        return $this;
    }


    /**
     * @param $user
     * @param $params
     * @param $currency
     * @param $amount
     * @return $this
     */
    private function checkWithdrawBigAmount($user, $params, $currency, $amount)
    {
        $checkAmountBig = env('WITHDRAW_VALIDATE_AMOUNT_LARGE', false);
        if ($checkAmountBig) {
            $amountCheck = BigNumber::new(-1)->mul($amount)->toString();
            $price = 1;
            if ($currency != Consts::CURRENCY_USDT) {
                try {
                    $price = app(PriceService::class)->getPrice(Consts::CURRENCY_USDT, $currency)->price;
                } catch (\Exception $ex) {
                    throw ValidationException::withMessages([
                        'amount' => "not_get_price_coin",
                    ]);
                }
            }

            $amountUsdt = BigNumber::new($amountCheck)->mul($price)->toString();
            if (BigNumber::new($amountUsdt)->comp(5000) >= 0) {
                // check address whitelist
                $checkAddressWhiteList = $user->userWithdrawalAddress()
                    ->where('wallet_address', \Arr::get($params, 'blockchain_address'))
                    ->where('network_id', \Arr::get($params, 'network_id'))
                    ->where('is_whitelist', Consts::TRUE)
                    ->first();
                if (!$checkAddressWhiteList) {
                    throw ValidationException::withMessages([
                        'amount' => 'amount_address_not_exist_in_whitelist',
                    ]);
                }
            }
        }
        return $this;
    }

    /**
     * @param $user_id
     * @return $this
     * @throws \Exception
     */
    private function validateUserSecurity($user_id)
    {
        $userSecuritySettings = UserSecuritySetting::find($user_id);

        if (!$userSecuritySettings->otp_verified) {
            throw new HttpException(422, __('exception.otp_not_verified'));
        }

        if (!$userSecuritySettings->identity_verified) {
            throw new HttpException(422, __('exception.identity_not_verified'));
        }

        $checkPhoneVerify = env('WITHDRAW_VALIDATE_VERIFY_PHONE', false);
        if ($checkPhoneVerify && !$userSecuritySettings->phone_verified) {
            throw new HttpException(422, __('exception.phone_not_verified'));
        }

        return $this;
    }
}
