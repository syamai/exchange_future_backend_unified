<?php

namespace App\Jobs;

use App\Models\User;
use App\Consts;
use App\Events\TransactionCreated;
use App\Http\Services\TransactionService;
use App\Notifications\WithdrawErrorsAlerts;
use Transaction\Models\Transaction;
use App\Utils\BigNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class Withdraw implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $transactionService;
    private $transaction = null;

    /**
     * Create a new job instance.
     *
     * @param Transaction $transaction
     */
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
        $this->transactionService = new TransactionService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->transactionService->withdrawToExternalAddress($this->transaction);
    }

    /**
     * The job failed to process.
     *
     * @param Exception $e
     * @return void
     */
    public function failed(Exception $e)
    {
        $this->transaction->error_detail = strval($e);
        $this->transaction->status = Consts::TRANSACTION_STATUS_ERROR;
        $this->transaction->save();


        $user = User::find($this->transaction->user_id);
        $user->notify(new WithdrawErrorsAlerts($this->transaction, $this->transaction->currency));
        // Mail::queue(new WithdrawErrorsAlerts($this->transaction, $this->transaction->currency));
        // $this->refundUserBalance($this->transaction);
    }

    public function refundUserBalance(Transaction $transaction)
    {
        $amount = BigNumber::new($transaction->amount)->sub($transaction->fee)->toString();

        DB::table($transaction->currency . '_accounts')
            ->where('id', $transaction->user_id)
            ->update([
                'available_balance' => DB::raw('available_balance - ' . $amount)
            ]);

        SendBalance::dispatchIfNeed($transaction->user_id, [$transaction->currency], Consts::TYPE_MAIN_BALANCE);

        event(new TransactionCreated($transaction, $transaction->user_id));
    }
}
