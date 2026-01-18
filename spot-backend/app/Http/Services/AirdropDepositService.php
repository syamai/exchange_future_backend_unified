<?php

namespace App\Http\Services;

use App\Consts;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Transaction\Models\Transaction;
use Transaction\Utils\BalanceCalculate;
use Transaction\Utils\Checker;
use App\Utils\BigNumber;
use App\Models\User;
use Exception;

class AirdropDepositService
{

    private $user;
    private $amount;
    private $currency;

    public function __construct($user, $amount, $currency)
    {
        $this->user = $user;
        $this->amount = $amount;
        $this->currency = $currency;
    }

    public function deposit(): void
    {
        $transaction = $this->withdrawal($this->user, $this->currency);
        $this->verifyWithdraw($transaction);
    }

    public function verifyWithdraw($transaction): bool
    {
        if (!$transaction || $transaction->status !== Transaction::PENDING_STATUS) {
            return true;
        }
        $this->internalVerify($transaction);
        return true;
    }
    /**
     * @param $transaction
     * @return mixed
     */
    private function internalVerify($transaction)
    {
        $this->updateStatusTransaction($transaction->transaction_id, Transaction::SUCCESS_STATUS);
        $amount = BalanceCalculate::internalCreateDeposit($transaction);
        return $this->createDepositTransaction($transaction, $amount);
    }
    private function createDepositTransaction($withdraw, $amount): Transaction
    {
        $transaction = new Transaction();
        $data = [
            'transaction_id' => Amanpuri_unique(),
            'user_id' => $this->user->id,
            'currency' => $withdraw->currency,
            'tx_hash' => '',
            'amount' => $amount,
            'from_address' => $withdraw->from_address,
            'to_address' => $withdraw->to_address,
            'blockchain_sub_address' => $withdraw->blockchain_sub_address,
            'fee' => 0,
            'transaction_date' => Carbon::now(),
            'status' => Consts::TRANSACTION_STATUS_SUCCESS,
            'type' => Consts::TRANSACTION_TYPE_DEPOSIT,
            'collect' => Consts::DEPOSIT_TRANSACTION_COLLECTED_STATUS,
            'created_at' => $now = Utils::currentMilliseconds(),
            'updated_at' => $now,
        ];
        $transaction->fill($data);
        $transaction->save();
        return $transaction;
    }

    private function updateStatusTransaction($transactionId, $status): void
    {
        Transaction::where('transaction_id', $transactionId)
            ->filterWithdraw()
            ->update([
                'status' => $status,
                'sent_at' => Utils::currentMilliseconds(),
            ]);
    }
    public function withdrawal($toUser, $currency): Transaction
    {
        $fromUser = $this->getImportUser();

        $this->getUserAddress($fromUser->id, $currency);
        $toAddress = $this->getUserAddress($toUser->id, $currency);
        $amount = BigNumber::new(-1)->mul($this->amount)->toString();
        $params = [
            'amount' => $amount,
            'blockchain_address' => $toAddress,
            'currency' => $currency,
            'is_new_address' => false,
            'is_no_memo' => false,
            'from_address' => null,
            'to_address' => $toAddress,
        ];
        // Send balance from import user bot
        return $this->createWithdrawTransaction($fromUser, $currency, $params);
        // Sub fee
    }
    private function createWithdrawTransaction($user, $currency, $params): Transaction
    {
        if (!$user) {
            throw new Exception();
        }
        $toAddress = Arr::get($params, 'to_address');
        $transaction = new Transaction();
        $data = [
            'transaction_id' => Amanpuri_unique(),
            'user_id' => $user->id,
            'currency' => $currency,
            'amount' => Arr::get($params, 'amount'),
            'fee' => 0,
            'from_address' => Arr::get($params, 'from_address'),
            'to_address' => $toAddress,
            'blockchain_sub_address' => Arr::get($params, 'blockchain_sub_address'),
            'status' => Consts::TRANSACTION_STATUS_PENDING,
            'transaction_date' => Carbon::now(),
            'is_external' => Checker::getTypeTransaction($currency, $toAddress),
            'created_at' => Utils::currentMilliseconds(),
            'updated_at' => Utils::currentMilliseconds(),
        ];
        $transaction->fill($data);
        $transaction->save();
        return $transaction;
    }
    public function getUserAddress($userId, $currency)
    {
        return DB::table($currency . '_accounts')->where('id', $userId)->value('blockchain_address');
    }

    public function getImportUser()
    {
        return User::where('email', config('airdrop.email'))->first();
    }
}
