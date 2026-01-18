<?php
/**
 * Created by PhpStorm.

 * Date: 6/20/19
 * Time: 2:24 PM
 */

namespace Transaction\Utils;

use App\Consts;
use App\Jobs\SendBalanceLogToWallet;
use App\Utils;
use App\Utils\BigNumber;
use Transaction\Http\Services\WalletService;

class UpdateBalance
{
    private $walletService;

    public function __construct()
    {
        $this->walletService = new WalletService();
    }

    public function verifyWithdrawInternal($transaction, $depositTransaction, $amount)
    {
        $this->withdrawUserTo($transaction, $depositTransaction->user_id, $amount);
        $this->withdrawUserForm($transaction);
    }

    public function verifyWithdrawExternal($transaction)
    {
        $currency = $transaction->currency;
        $userId = $transaction->user_id;

        $balanceTransaction = BalanceCalculate::approvedWithdraw($transaction);

        $this->walletService->updateUserBalanceRaw($currency, $userId, $balanceTransaction, 0);

        //send balance log to wallet
        if (env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false) && env('SEND_BALANCE_LOG_TO_WALLET', false)) {
            $amountWithdraw = $balanceTransaction;
            if ($amountWithdraw < 0) {
                $amountWithdraw = BigNumber::new($amountWithdraw)->mul(-1)->toString();
            }

            SendBalanceLogToWallet::dispatch([
                'userId' => $transaction->user_id,
                'walletType' => 'SPOT',
                'type' => 'WITHDRAWAL',
                'currency' => $currency,
                'currencyAmount' => $amountWithdraw,
                'currencyFeeAmount' => $transaction->fee,
                'currencyAmountWithoutFee' => BigNumber::new($amountWithdraw)->sub($transaction->fee)->toString(),
                'date' => Utils::currentMilliseconds()
            ])->onQueue(Consts::QUEUE_BALANCE_WALLET);
        }

    }

    public function withdrawUserTo($transaction, $userId, $amount)
    {
        $currency = $transaction->currency;

        $this->walletService->updateUserBalanceRaw($currency, $userId, $amount, $amount);
        //send balance log to wallet
        if (env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false) && env('SEND_BALANCE_LOG_TO_WALLET', false)) {
            $amountWithdraw = $amount;
            if ($amountWithdraw < 0) {
                $amountWithdraw = BigNumber::new($amountWithdraw)->mul(-1)->toString();
            }

            SendBalanceLogToWallet::dispatch([
                'userId' => $transaction->user_id,
                'walletType' => 'SPOT',
                'type' => 'DEPOSIT',
                'currency' => $currency,
                'currencyAmount' => $amountWithdraw,
                'currencyFeeAmount' => "0",
                'currencyAmountWithoutFee' => $amountWithdraw,
                'date' => Utils::currentMilliseconds()
            ])->onQueue(Consts::QUEUE_BALANCE_WALLET);
        }
    }

    public function withdrawUserForm($transaction)
    {
        $currency = $transaction->currency;
        $formUserId = $transaction->user_id;

        $amount = BalanceCalculate::approvedWithdraw($transaction);

        $this->walletService->updateUserBalanceRaw($currency, $formUserId, $amount, 0);

        //send balance log to wallet
        if (env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false) && env('SEND_BALANCE_LOG_TO_WALLET', false)) {
            $amountWithdraw = $amount;
            if ($amountWithdraw < 0) {
                $amountWithdraw = BigNumber::new($amountWithdraw)->mul(-1)->toString();
            }

            SendBalanceLogToWallet::dispatch([
                'userId' => $transaction->user_id,
                'walletType' => 'SPOT',
                'type' => 'WITHDRAWAL',
                'currency' => $currency,
                'currencyAmount' => $amountWithdraw,
                'currencyFeeAmount' => $transaction->fee,
                'currencyAmountWithoutFee' => BigNumber::new($amountWithdraw)->sub($transaction->fee)->toString(),
                'date' => Utils::currentMilliseconds()
            ])->onQueue(Consts::QUEUE_BALANCE_WALLET);
        }
    }
}
