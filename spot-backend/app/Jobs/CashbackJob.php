<?php

namespace App\Jobs;

use App\Consts;
use App\Http\Services\AirdropService;
use Carbon\Carbon;
use App\Models\AirdropAmalAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Utils\BigNumber;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Models\DividendCashbackHistory;
use Transaction\Models\Transaction;
use App\Utils;

class CashbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private $data;
    private $cashBackId;
    public $tries = Consts::UNLOCK_ATTEMPTS;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, $cashBackId = null)
    {
        $this->data = $data;
        $this->cashBackId = $cashBackId;
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
            $this->cashback($this->data, $this->cashBackId);
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function cashback($data, $cashBackId)
    {
        $balance = AirdropAmalAccount::where('id', $data['user_id'])->first();
        if (BigNumber::new($balance->balance_bonus)->comp($data['amount']) < 0) {
            return $this->updateStatus($cashBackId, false);
        }
        return $this->updateBalance($data, $cashBackId);
    }

    public function updateBalance($data, $cashBackId)
    {
        $cashback = AirdropAmalAccount::where('id', $data['user_id'])
            ->update([
                'balance_bonus' => DB::raw('balance_bonus - ' . $data['amount'])
            ]);
        if ($cashback) {
            $this->createWithdrawTransaction($data);
            app(AirdropService::class)->updateTotalBonus(BigNumber::new($data['amount'])->mul(-1), Consts::CURRENCY_AMAL);
            return $this->updateStatus($cashBackId, true);
        }
    }

    public function updateStatus($cashBackId, $success)
    {
        return DividendCashbackHistory::where('cashback_id', $cashBackId)
        ->update([
            'status' => $success == true ? Consts::AIRDROP_SUCCESS : Consts::AIRDROP_FAIL
        ]);
    }

    private function createWithdrawTransaction($data)
    {
        $data = (object) $data;
        $transaction = new Transaction();
        $data = [
            'transaction_id' => Amanpuri_unique(),
            'user_id' => $data->user_id,
            'currency' => Consts::CURRENCY_AMAL,
            'tx_hash' => '',
            'amount' => BigNumber::new($data->amount)->mul(-1)->toString(),
            'from_address' => Consts::CASHBACK,
            'to_address' => $this->getUserAddress($data->user_id, Consts::CURRENCY_AMAL),
            'blockchain_sub_address' => "",
            'fee' => 0,
            'transaction_date' => Carbon::now(),
            'status' => Consts::TRANSACTION_STATUS_SUCCESS,
            'type' => Consts::TRANSACTION_TYPE_WITHDRAW,
            'collect' => Consts::DEPOSIT_TRANSACTION_COLLECTED_STATUS,
            'created_at' => Utils::dateTimeToMilliseconds(Carbon::now()),
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
