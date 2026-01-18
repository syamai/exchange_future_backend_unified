<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Transaction\Jobs\ExpiredWithdrawJob;
use App\Consts;
use App\Utils;
use App\Http\Services\TransactionService;
use Transaction\Models\Transaction;
use Exception;

class CheckExpiredWithdrawals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check_expired_withdrawals:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check expired withdrawal requests';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $transactionService = new TransactionService();
        $expiredWithdraws = Transaction::where('created_at', '<', Utils::previous24hInMillis())->where('status', Transaction::PENDING_STATUS)->filterWithdraw()->pluck('transaction_id')->toArray();

        foreach ($expiredWithdraws as $transaction_id) {
            DB::beginTransaction();
            try {
                $transactionService->cancelTransaction(compact('transaction_id'));
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                logger()->error($e->getMessage());
            }
        }
    }
}
