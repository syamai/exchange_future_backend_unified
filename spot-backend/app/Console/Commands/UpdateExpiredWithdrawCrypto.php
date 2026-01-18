<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\TransactionService;
use App\Utils;
use Illuminate\Console\Command;
use Transaction\Models\Transaction;

class UpdateExpiredWithdrawCrypto extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:withdraw-coin-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        logger()->info('START UPDATE EXPIRED WITHDRAW CRYPTO');
        try {
            $expiredTime = Utils::previous24hInMillis();
            $transactions = Transaction::query()->where([
                ['created_at', '<', $expiredTime],
                ['status', Transaction::PENDING_STATUS]
            ])->filterWithdraw()->get();
            foreach ($transactions as $transaction) {
                app(TransactionService::class)->cancelTransaction(['transaction_id' => $transaction->id]);
            }
        } catch (\Exception $e) {
            logger()->error('FAIL TO UPDATE');
            throw $e;
        }
    }
}
