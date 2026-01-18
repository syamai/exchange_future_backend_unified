<?php
/**
 * Created by PhpStorm.
 * Date: 5/21/19
 * Time: 1:49 PM
 */

namespace Transaction\Http\Services;

use App\Consts;
use App\Facades\FormatFa;
use App\Jobs\SendNotifyTelegram;
use App\Utils;
use App\Utils\BigNumber;
use App\Models\User;
use App\Notifications\WithdrawalVerifyAlert;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Transaction\Jobs\WithdrawJob;
use Transaction\Models\Transaction;
use App\Models\AMALNetStatistic;
use Transaction\Utils\BalanceCalculate;
use Transaction\Utils\Checker;
use Transaction\Utils\UpdateBalance;
use Transaction\Utils\UserInformation;
use App\Http\Services\TransactionService as TransactionServiceApp;
use App\Jobs\UpdateTotalVolumeDepositJob;
use Illuminate\Support\Facades\DB;

/**
 * Class WithdrawVerifyService
 * @package Transaction\Http\Services
 */
class WithdrawVerifyService
{
    /**
     * @var TransactionService
     */
    private $transactionService;

    /**
     * WithdrawVerifyService constructor.
     * @param TransactionService $transactionService
     */
    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * @param $transactionId
     * @return mixed
     * @throws \Exception
     */
    public function verify($transactionId)
    {
        $transaction = $this->transactionService->getWithdrawTransaction($transactionId);
        $results = $this->verifyWithdraw($transaction);
        $this->notify($transaction, $results);
        return $transaction;
    }

    /**
     * @param $transaction
     * @param $results
     */
    private function notify($transaction, $results)
    {
        if (Checker::isInternalTransaction($transaction->currency, $transaction->to_address)) {
            list($transaction, $depositTransaction) = $results;
            $this->transactionService->notifyTransactionCreated($transaction);
            $this->transactionService->notifyTransactionCreated($depositTransaction);
        } else {
            $this->transactionService->notifyTransactionCreated($transaction);
        }
    }

    public function adminSendVerifyWithdrawEmail($transactionId)
    {
        $transaction = $this->transactionService->getWithdrawTransaction($transactionId);
        if ($transaction == null) {
            throw new HttpException(422, __('exception.invalid_transaction'));
        }
        if ($transaction->approved_by != null || $transaction->tx_hash != null) {
            throw new HttpException(422, __('exception.transaction_executed_canceled'));
        }
        User::find($transaction->user_id)->notify(new WithdrawalVerifyAlert($transaction));
    }

    /**
     * @param $transactionId
     * @return array
     * @throws \Exception
     */
    public function verifyWithdraw($transaction_id)
    {
        $transaction = Transaction::lockForUpdate()->where(compact('transaction_id'))->filterWithdraw()->first();
        if ($transaction == null) {
            throw new HttpException(422, __('exception.invalid_transaction'));
        }
        if ($transaction->status !== Transaction::PENDING_STATUS || $transaction->approved_by != null || $transaction->tx_hash != null) {
            throw new HttpException(422, __('exception.transaction_executed_canceled'));
        }
        $expiredTime = Utils::previous24hInMillis();
        if (BigNumber::new($expiredTime)->comp($transaction->created_at) > 0) {
            $transactionServiceApp = new TransactionServiceApp();
            return $transactionServiceApp->cancelTransaction(compact('transaction_id'));
        }
        return $this->selectVerify($transaction);
    }

    private function selectVerify($transaction)
    {
        //$currency = FormatFa::getPlatformCurrency($transaction->currency);
        $currency = $transaction->currency;
        $networkId = $transaction->network_id;
        if (Checker::isInternalTransaction($currency, $transaction->to_address, $networkId)) {
            return $this->internalVerify($transaction);
        }
        return $this->externalVerify($transaction);
    }

    /**
     * @param $transaction
     * @return mixed
     */
    private function externalVerify($transaction)
    {
        $data = [
            'approved_by' => $transaction->user_id,
        ];
        $this->updateTransaction($transaction->transaction_id, $data);
        $this->transactionService->notifyTransactionCreated($transaction);
        WithdrawJob::dispatch($transaction)->onQueue(Consts::QUEUE_WITHDRAW);
		// send notify deposit telegram
		$user = User::find($transaction->user_id);
		SendNotifyTelegram::dispatch('withdraw', 'User withdraw check: '.$user->email. " ({$transaction->currency}: {$transaction->amount})");

        return $transaction;
    }

    /**
     * @param $transaction
     * @return mixed
     */
    private function internalVerify($transaction)
    {
        $data = [
            'status' => Transaction::SUCCESS_STATUS,
            'sent_at' => Utils::currentMilliseconds(),
            'approved_by' => $transaction->user_id,
        ];
        if ($transaction->currency == Consts::CURRENCY_AMAL) {
            $this->updateAMALNet($transaction);
        }
        $transaction = $this->updateTransaction($transaction->transaction_id, $data);
        $amount = BalanceCalculate::internalCreateDeposit($transaction);
        $depositTransaction = $this->createDepositTransaction($transaction, $amount);
        app(UpdateBalance::class)->verifyWithdrawInternal($transaction, $depositTransaction, $amount);

        $isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
        if ($isSpotMainBalance) {
            $this->transactionService->sendMEDepositWithdraw($depositTransaction, $depositTransaction->user_id, $depositTransaction->currency, $amount, true, false);
        }

        $this->transactionService->notifyWithdrawVerify($transaction, $depositTransaction);
        return $transaction;
    }

    public function updateAMALNet($transaction)
    {
        $totalOut = BigNumber::new($transaction->amount)->mul(-1)->toString();

        $userId = $transaction->user_id;
        $date = Carbon::now()->toDateString();
        $record = AMALNetStatistic::where('user_id', $userId)
            ->where('statistic_date', $date)
            ->first();
        if (!$record) {
            return AMALNetStatistic::create([
                'user_id' => $userId,
                'statistic_date' => $date,
                'amal_in' => 0,
                'amal_out' => $totalOut
            ]);
        }
        return DB::connection('master')->table('amal_net_statistics')
            ->where('user_id', $userId)
            ->where('statistic_date', $date)
            ->increment('amal_out', $totalOut);
    }

    private function updateTransaction($transactionId, $data)
    {
        $transaction = Transaction::where('transaction_id', $transactionId)
            ->filterWithdraw()->first();
        $transaction->fill($data);
        $transaction->save();

        return $transaction;
    }

    private function createDepositTransaction($withdraw, $amount)
    {
        $userId = UserInformation::getUserIdDepositByTransaction($withdraw);
        $transaction = new Transaction();
        $data = [
            'transaction_id' => Amanpuri_unique(),
            'user_id' => $userId,
            'currency' => $withdraw->currency,
            'network_id' => $withdraw->network_id,
            'tx_hash' => '',
            'amount' => $amount,
            'from_address' => $withdraw->from_address,
            'to_address' => $withdraw->to_address,
            'blockchain_sub_address' => $withdraw->blockchain_sub_address,
            'fee' => 0,
            'transaction_date' => Carbon::now(),
            'status' => Consts::TRANSACTION_STATUS_SUCCESS,
            'type' => Consts::TRANSACTION_TYPE_DEPOSIT,
            'created_at' => $now = Utils::currentMilliseconds(),
            'updated_at' => $now,
            'collect' => Consts::DEPOSIT_TRANSACTION_COLLECTED_STATUS,
        ];
        $transaction->fill($data);
        $transaction->save();

        // UpdateTotalVolumeDepositJob::dispatch($transaction)->onQueue(Consts::QUEUE_UPDATE_DEPOSIT);

        return $transaction;
    }
}
