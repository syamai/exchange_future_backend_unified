<?php

namespace App\Jobs;

use App\Consts;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Utils\BigNumber;
use Exception;
use App\Http\Services\AirdropDepositService;
use App\Utils;
use App\Models\DepositCollect;
use Transaction\Models\Transaction;

class UpdateTotalVolumeDepositJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private $transaction;
    public $tries = Consts::UNLOCK_ATTEMPTS;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //NOTE: uncomment for initialing Deposite Amount to calculate trading-volume only
        //This job follow by UpdateTotalDepositAmountCommand


        // \DB::beginTransaction();
        // try {
        //     $this->updateTotalVolumeDeposit($this->transaction);
        //     \DB::commit();
        // } catch (Exception $exception) {
        //     \DB::rollBack();
        //     throw $exception;
        // }
    }

    public function updateTotalVolumeDeposit($transaction)
    {
        if (BigNumber::new($transaction->amount)->comp(0) < 0) {
            return false;
        }
        $transaction->amount = Utils::convertToBTC($transaction->amount, $transaction->currency);
        $record = $this->checkExistUser($transaction->user_id);
        if ($record) {
            $this->updateVolumeDeposit($transaction->amount, $record);
        } else {
            $this->createRecordDeposit($transaction);
        }
    }
    public function checkExistUser($userId)
    {
        $record = DepositCollect::where('user_id', $userId)->first();
        if (!$record) {
            return false;
        }
        return $record;
    }
    public function updateVolumeDeposit($amount, $record)
    {
        $record->amount = BigNumber::new($record->amount)->add($amount)->toString();
        $record->save();
    }

    public function createRecordDeposit($transaction)
    {
        $data = [
            'user_id' => $transaction->user_id,
            'amount' => $transaction->amount,
            'type' => 'deposit',
            'created_at' => Carbon::today()->toDateString(),
            'updated_at' => Carbon::today()->toDateString(),

        ];
        return DepositCollect::create($data);
    }
}
