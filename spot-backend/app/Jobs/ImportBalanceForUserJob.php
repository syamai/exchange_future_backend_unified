<?php

namespace App\Jobs;

use App\Consts;
use App\Http\Services\ImportDataService;
use Illuminate\Support\Arr;
use Transaction\Utils\UpdateBalance;
use Transaction\Utils\UserInformation;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Transaction\Http\Services\WalletService;
use Transaction\Models\Transaction;
use Transaction\Utils\BalanceCalculate;
use Transaction\Utils\Checker;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportBalanceForUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $balance;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user, $balance)
    {
        $this->user = $user;
        $this->balance = $balance;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::beginTransaction();
        try {
            $transaction = $this->withdrawal($this->user, Consts::CURRENCY_AMAL);
            $this->verifyWithdraw($transaction);
            DB::commit();
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            throw $ex;
        }
    }


    public function verifyWithdraw($transaction)
    {
        if (!$transaction || $transaction->status !== Transaction::PENDING_STATUS) {
            return true;
        }

        $depositTransaction = $this->internalVerify($transaction);
    }

    /**
     * @param $transaction
     * @return mixed
     */
    private function internalVerify($transaction): mixed
    {
        $this->updateStatusTransaction($transaction->transaction_id, Transaction::SUCCESS_STATUS);

        $amount = BalanceCalculate::internalCreateDeposit($transaction);
        $depositTransaction = $this->createDepositTransaction($transaction, $amount);
        app(UpdateBalance::class)->verifyWithdrawInternal($transaction, $depositTransaction, $amount);

        return $depositTransaction;
    }


    private function createDepositTransaction($withdraw, $amount): Transaction
    {
        $userId = UserInformation::getUserIdDepositByTransaction($withdraw);
        $transaction = new Transaction();

        $data = [
            'transaction_id' => Amanpuri_unique(),
            'user_id' => $userId,
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
            'created_at' => Utils::dateTimeToMilliseconds(Carbon::now()),
            'updated_at' => Utils::currentMilliseconds(),
        ];

        $transaction->fill($data);
        $transaction->save();

        return $transaction;
    }

    /**
     * @param $transactionId
     * @param $status
     * @return mixed
     */
    private function updateStatusTransaction($transactionId, $status): mixed
    {
        return Transaction::where('transaction_id', $transactionId)
            ->filterWithdraw()
            ->update([
                'status' => $status,
                'sent_at' => Utils::currentMilliseconds(),
            ]);
    }

    public function withdrawal($toUser, $currency): Transaction
    {
        $importDataService = new ImportDataService();
        $fromUser = $importDataService->getImportUser();

        $fromAddress = $this->getUserAddress($fromUser->id, $currency);
        $toAddress = $this->getUserAddress($toUser->id, $currency);
        $amount = BigNumber::new(-1)->mul($this->balance->amount)->toString();

        // 1. amount: "-40”        //—> Amount
        // 2. blockchain_address: “0xdcB10F61732D439db1902b3D4B7BDE82B874409d” //—> Receive address
        // 3. currency: "amal"
        // 4. is_new_address: false        //—> Set false
        // 5. is_no_memo: false            //—> Set false
        // 6. lang: "en"
        // 7. otp: “543132”                //—> Remove
        $params = [
            'amount' => $amount,
            'blockchain_address' => $toAddress,
            'currency' => $currency,
            'is_new_address' => false,
            'is_no_memo' => false,
            'from_address' => $fromAddress,
            'to_address' => $toAddress,
        ];
        // Send balance from import user bot
        $transaction = $this->createWithdrawTransaction($fromUser, $currency, $params);

        // Sub fee
        $balancePay = $amount;

        // Send balance from import user bot
        $walletService = app(WalletService::class);
        $walletService->updateUserBalanceRaw($currency, $fromUser->id, 0, $balancePay);

        return $transaction;
    }

    /**
     * @param $user
     * @param $currency
     * @param $params
     * @param $withdrawLimit
     * @return Transaction
     */
    private function createWithdrawTransaction($user, $currency, $params): Transaction
    {
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
}
