<?php
/**
 * Created by PhpStorm.

 * Date: 6/17/19
 * Time: 5:58 PM
 */

namespace Transaction\Http\Services;

use App\Http\Services\Blockchain\SotatekBlockchainService;
use App\Http\Services\HotWalletService;
use App\Http\Services\TransactionService as TransactionServiceApp;
use App\Models\SumsubKYC;
use App\Models\User;
use Transaction\Utils\BalanceCalculate;

class WithdrawJobService
{
    public function approveWallet($transaction)
    {
        $params = $this->getParams($transaction);
        $tx_hash = $this->approveTransaction($params);

        if ($tx_hash) {
            $transaction->tx_hash = $tx_hash;
            $transaction->save();
        } else {
            // if request to wallet fail -> cancel transaction and refund balance for user
            logger()->error('CANCEL TRANSACTION ==============' . json_encode($transaction->transaction_id));
            $transactionServiceApp = new TransactionServiceApp();
            $transactionServiceApp->cancelTransactionWhenRequestToWalletFail(['transaction_id' => $transaction->transaction_id]);
        }
    }

    public function approveTransaction($params)
    {
        $hotWalletService = new HotWalletService();
        return $hotWalletService->approveTransaction($params);
    }

    private function getParams($transaction)
    {
        $currency = $transaction->currency;
        $service = new SotatekBlockchainService((object)['coin' =>$currency, 'network_id' => $transaction->network_id]);

        $amount = BalanceCalculate::approvedWalletWithdraw($transaction);
        $amount = $service->fixTransactionAmount($amount, false);
        $fullName = null;
        $sumsubApplicantId = null;
        $userKyc = SumsubKYC::where('user_id', $transaction->user_id)->first();
        if ($userKyc) {
            $fullName = $userKyc->full_name;
            $sumsubApplicantId = $userKyc->id_applicant ?? null;
        }
        $accountId = "";
        $user = User::find($transaction->user_id);
        if ($user) {
            $accountId = $user->uid;
        }

        return [
            'toAddress' => $transaction->to_address,
            'amount' => $amount,
            'currency' => $transaction->currency,
            'network_id' => $transaction->network_id,
            'userId' => $transaction->user_id,
            'accountId' => $accountId,
            'fullName' => $fullName,
            'sumsubApplicantId' => $sumsubApplicantId
        ];
    }
}
