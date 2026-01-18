<?php

namespace App\Jobs;

use App\Consts;
use App\Utils;
use App\Utils\BigNumber;
use App\Models\OrderTransaction;
use App\Models\UserTransaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use \Exception;

class CalculateReferralCommission extends RedisQueueJob
{
    private $transactionId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($json)
    {
        $data = json_decode($json);
        $this->transactionId = $data[0];
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
        $transaction = OrderTransaction::find($this->transactionId);

        if (!$transaction) {
            Log::error('CalculateReferralCommission. Cannot find transaction id: ' . $this->transactionId);
            return;
        }
        $createdAt = $transaction->created_at;

        $currency = $transaction->currency;
        $coin = $transaction->coin;
        $buyer = User::find($transaction->buyer_id);
        $seller = User::find($transaction->seller_id);

        DB::beginTransaction();
        try {
            if ($buyer->referrer_id) {
                $this->addUserCommission($buyer->referrer_id, $transaction->coin, $transaction->buy_fee, $transaction);
            }

            if ($seller->referrer_id) {
                $this->addUserCommission(
                    $seller->referrer_id,
                    $transaction->currency,
                    $transaction->sell_fee,
                    $transaction
                );
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('CalculateReferralCommission. Failed to calculate commission for transaction: '
                . $this->transactionId);
            Log::error($e);
            throw $e;
        }
    }

    private function addUserCommission($userId, $currency, $fee, $transaction)
    {
        $commission = $this->calculateCommission($userId, $fee);
        DB::table("spot_{$currency}_accounts")
            ->where('id', $userId)
            ->update([
                'balance' => DB::raw("balance + $commission"),
                'available_balance' => DB::raw("available_balance + $commission")
            ]);

        UpdateUserTransaction::dispatchIfNeed(
            Consts::USER_TRANSACTION_TYPE_COMMISSION,
            $transaction->id,
            $userId,
            $currency,
            $commission
        );
    }

    private function calculateCommission($userId, $fee)
    {
        $commissionRate = $this->getCommissionRate($userId);
        return BigNumber::new($fee)->mul("{$commissionRate}")->toString();
    }

    private function getCommissionRate($userId)
    {
        $key = Consts::COMMISSION_RATE_KEY . $userId;
        if (Cache::has($key)) {
            return Cache::get($key);
        }
        return Consts::COMMISSION_RATE_DEFAULT;
    }
}
