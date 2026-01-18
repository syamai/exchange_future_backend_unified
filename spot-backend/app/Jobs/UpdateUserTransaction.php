<?php

namespace App\Jobs;

use App\Consts;
use App\Models\AdminDeposits;
use App\Utils;
use App\Utils\BigNumber;
use App\Http\Services\TransactionService;
use App\Http\Services\PriceService;
use App\Models\Order;
use App\Models\OrderTransaction;
use Transaction\Models\Transaction;
use App\Models\UserTransaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use \Exception;

class UpdateUserTransaction extends RedisQueueJob
{
    private $type;
    private $transactionId;
    private $userId;
    private $currency;
    private $amount;

    private $transactionService;
    private $priceService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($json)
    {
        $data = json_decode($json);
        $this->type = $data[0];
        $this->transactionId = $data[1];
        $this->userId = $data[2];
        $this->currency = $data[3];
        $this->amount = $data[4];

        $this->transactionService = new TransactionService();
        $this->priceService = new PriceService();
    }

    protected static function shouldAddToQueue()
    {
        return true;
    }

    protected static function getNextRun()
    {
        return static::currentMilliseconds() + 10000;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $transaction = $this->getTransaction();

        if (!$transaction) {
            Log::error('Cannot find transaction type: ' . $this->type . ', id: ' . $this->transactionId);
            return;
        }
        $createdAt = $transaction->created_at;
        if ($this->type == Consts::USER_TRANSACTION_TYPE_ADMIN_DEPOSIT) {
            $createdAt = $createdAt->timestamp * 1000;
        }

        $lastUserTransaction = UserTransaction::select('ending_balance', 'created_at')
            ->where('user_id', $this->userId)
            ->where('currency', $this->currency)
            ->orderBy('created_at', 'desc')
            ->first();
        $ending_balance = $lastUserTransaction ? $lastUserTransaction->ending_balance : 0;
        if ($lastUserTransaction && $lastUserTransaction->created_at > $createdAt) {
            $createdAt = $lastUserTransaction->created_at + 1;
            logger(json_encode($lastUserTransaction));
        }

        $userTransaction = new UserTransaction();
        $userTransaction->user_id = $this->userId;
        $userTransaction->email = User::find($this->userId)->email;
        $userTransaction->friend_email = $this->getFriendEmail($transaction);
        $userTransaction->currency = $this->currency;
        if (BigNumber::new($this->amount)->comp(0) < 0) {
            $userTransaction->credit = BigNumber::new($this->amount)->mul(-1)->toString();
        } else {
            $userTransaction->debit = $this->amount;
        }
        $userTransaction->ending_balance = BigNumber::new($ending_balance)->add($this->amount)->toString();
        $userTransaction->type = $this->type;
        $userTransaction->transaction_id = $this->transactionId;
        $userTransaction->created_at = $createdAt;
        $this->calculateCommissionBtc($userTransaction);
        $userTransaction->save();
    }

    private function getTransaction()
    {
        switch ($this->type) {
            case Consts::USER_TRANSACTION_TYPE_TRANSFER:
                return Transaction::select('id', 'created_at')->where('id', $this->transactionId)->first();
                break;
            case Consts::USER_TRANSACTION_TYPE_ADMIN_DEPOSIT:
                return AdminDeposits::select('id', 'created_at')->where('id', $this->transactionId)->first();
            case Consts::USER_TRANSACTION_TYPE_TRADING:
            case Consts::USER_TRANSACTION_TYPE_COMMISSION:
                return OrderTransaction::where('id', $this->transactionId)->first();
                break;
        }
    }

    private function getFriendEmail($transaction)
    {
        if ($this->type == Consts::USER_TRANSACTION_TYPE_COMMISSION) {
            $friendId = null;
            if ($this->currency == $transaction->currency) {
                $friendId = $transaction->seller_id;
            } elseif ($this->currency == $transaction->coin) {
                $friendId = $transaction->buyer_id;
            }
            if (!$friendId) {
                Log::error('Cannot find friend id, userId: ' . $this->userId . ', currency: ' . $this->currency
                    . ', transaction: ' . json_encode($transaction));
                return;
            }

            return User::find($friendId)->email;
        }
    }

    private function calculateCommissionBtc($userTransaction)
    {
        if ($this->type !== Consts::USER_TRANSACTION_TYPE_COMMISSION) {
            return;
        }
        $userTransaction->commission_btc = $this->priceService->convertAmount($this->amount, $this->currency, Consts::CURRENCY_BTC);
    }
}
