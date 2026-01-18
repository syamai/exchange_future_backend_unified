<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\JobCheckpoints;
use App\Models\UserAssetTransactions;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateUserTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:user-transaction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'User transaction deposit & withdraw';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $logTime = Carbon::now()->format('Y-m-d H:i:s');
        $this->info("{$logTime} START-[{$this->signature}]{$this->description} =====================\n");
        
        $jobName = 'calculate_user_transactions';
        $now = round(microtime(true) * 1000);
        $chunkSize = config('constants.chunk_limit_checkpoint');
        
        $this->info("Calculating transactions (chunked {$chunkSize} rows)...");

        try {
            // Lấy last_processed_id checkpoint
            $lastProcessedId = JobCheckpoints::jobName($jobName)->value('last_calculated_at') ?? 0;

            // Subquery giá coin mới nhất
            $latestPrices = DB::table('prices as p1')
                ->select('p1.coin', 'p1.price')
                ->whereRaw('p1.created_at = (
            SELECT MAX(p2.created_at)
            FROM prices p2
            WHERE p2.coin = p1.coin AND p2.currency = ?
        )', [Consts::CURRENCY_USDT]);

            // Truy vấn 100 transactions tiếp theo sau checkpoint
            $transactions = DB::table('transactions as t')
                ->join('users as u', 'u.id', 't.user_id')
                ->where('u.type', '<>', 'bot')
                ->leftJoinSub(
                    $latestPrices,
                    'p',
                    fn($join) =>
                    $join->on('t.currency', '=', 'p.coin')
                )
                ->select(
                    't.id',
                    't.user_id',
                    't.currency',
                    't.status',
                    DB::raw("CASE WHEN t.currency = 'usdt' THEN 1 ELSE COALESCE(p.price, 0) END as price_real"),
                    DB::raw("SUM(CASE WHEN t.amount > 0 AND t.status = 'success' THEN t.amount ELSE 0 END) as total_deposit"),
                    DB::raw("SUM(CASE WHEN t.amount > 0 AND t.status = 'pending' THEN t.amount ELSE 0 END) as total_pending_deposit"),
                    DB::raw("SUM(CASE WHEN t.amount > 0 AND t.status = 'cancel' THEN t.amount ELSE 0 END) as total_cancel_deposit"),

                    DB::raw("SUM(CASE WHEN t.amount < 0 AND t.status = 'success' THEN t.amount ELSE 0 END) as total_withdrawal"),
                    DB::raw("SUM(CASE WHEN t.amount < 0 AND t.status = 'pending' THEN t.amount ELSE 0 END) as total_pending_withdraw"),
                    DB::raw("SUM(CASE WHEN t.amount < 0 AND t.status = 'cancel' THEN t.amount ELSE 0 END) as total_cancel_withdraw"),

                    DB::raw("SUM(CASE WHEN t.amount > 0 AND t.status = 'success' THEN t.amount * (CASE WHEN t.currency = 'usdt' THEN 1 ELSE COALESCE(p.price, 0) END) ELSE 0 END) as deposit_value"),
                    DB::raw("SUM(CASE WHEN t.amount > 0 AND t.status = 'pending' THEN t.amount * (CASE WHEN t.currency = 'usdt' THEN 1 ELSE COALESCE(p.price, 0) END) ELSE 0 END) as pending_deposit_value"),
                    DB::raw("SUM(CASE WHEN t.amount > 0 AND t.status = 'cancel' THEN t.amount * (CASE WHEN t.currency = 'usdt' THEN 1 ELSE COALESCE(p.price, 0) END) ELSE 0 END) as cancel_deposit_value"),
                    
                    DB::raw("SUM(CASE WHEN t.amount < 0 AND t.status = 'success' THEN t.amount * (CASE WHEN t.currency = 'usdt' THEN 1 ELSE COALESCE(p.price, 0) END) ELSE 0 END) as withdraw_value"),
                    DB::raw("SUM(CASE WHEN t.amount < 0 AND t.status = 'pending' THEN t.amount * (CASE WHEN t.currency = 'usdt' THEN 1 ELSE COALESCE(p.price, 0) END) ELSE 0 END) as pending_withdraw_value"),
                    DB::raw("SUM(CASE WHEN t.amount < 0 AND t.status = 'cancel' THEN t.amount * (CASE WHEN t.currency = 'usdt' THEN 1 ELSE COALESCE(p.price, 0) END) ELSE 0 END) as cancel_withdraw_value"),
                )
                ->where('t.id', '>', $lastProcessedId)
                ->orderBy('t.id')
                ->groupBy('t.user_id', 't.currency', 't.status')
                ->limit($chunkSize)
                ->get();

            if ($transactions->isEmpty()) {
                $this->info("{$logTime} END-[{$this->signature}]{$this->description} | No more transactions to process. \n");
                return;
            }

            // Xử lý từng record
            $count = $this->processBatch($transactions, $now);

            // Lưu checkpoint mới theo ID cuối cùng
            $newLastId = $transactions->last()->id;

            JobCheckpoints::updateOrCreate(
                ['job' => $jobName],
                [
                    'last_calculated_at' => $newLastId,
                    'updated_at' => $now,
                ]
            );
        } catch (\Throwable $e) {
            Log::channel('spot_overview')->error('Error in [spot:user-transaction] job', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }


        $this->info("Processed $count records. Last ID: $newLastId");

        $this->info("{$logTime} END-[{$this->signature}]{$this->description} ====counts: [{$count}] | jobName:[{$jobName}] - {$newLastId}======job completed===========\n");
    }


    protected function processBatch($transactions, $now): int
    {
        $count = 0;

        foreach ($transactions as $tx) {
            $record = UserAssetTransactions::firstOrNew([
                'user_id' => $tx->user_id,
                'currency' => $tx->currency,
                'status' => $tx->status,
            ]);

            if ($tx->total_deposit > 0) {
                $category = match ($tx->status) {
                    Consts::TRANSACTION_STATUS_SUCCESS => Consts::TRANSACTION_TYPE_DEPOSIT . '_' . Consts::TRANSACTION_STATUS_SUCCESS,
                    Consts::TRANSACTION_STATUS_PENDING => Consts::TRANSACTION_TYPE_DEPOSIT . '_' . Consts::TRANSACTION_STATUS_PENDING,
                    Consts::TRANSACTION_STATUS_CANCEL  => Consts::TRANSACTION_TYPE_DEPOSIT . '_' . Consts::TRANSACTION_STATUS_CANCEL,
                    default => Consts::TRANSACTION_TYPE_DEPOSIT . '_unknown',
                };
            } else {
                $category = match ($tx->status) {
                    Consts::TRANSACTION_STATUS_SUCCESS => Consts::TRANSACTION_TYPE_WITHDRAW . '_' . Consts::TRANSACTION_STATUS_SUCCESS,
                    Consts::TRANSACTION_STATUS_PENDING => Consts::TRANSACTION_TYPE_WITHDRAW . '_' . Consts::TRANSACTION_STATUS_PENDING,
                    Consts::TRANSACTION_STATUS_CANCEL  => Consts::TRANSACTION_TYPE_WITHDRAW . '_' . Consts::TRANSACTION_STATUS_CANCEL,
                    default => Consts::TRANSACTION_TYPE_WITHDRAW . '_unknown',
                };
            }

            $record->category = $category;
            $record->price_real = $tx->price_real;

            $record->total_deposit = $tx->total_deposit;
            $record->total_pending_deposit = $tx->total_pending_deposit;
            $record->total_withdraw = $tx->total_withdrawal;
            $record->total_pending_withdraw = $tx->total_pending_withdraw;
            $record->total_cancel_withdraw = $tx->total_cancel_withdraw;

            $record->deposit_value = $tx->deposit_value;
            $record->pending_deposit_value = $tx->pending_deposit_value;
            $record->cancel_deposit_value = $tx->cancel_deposit_value;

            $record->withdraw_value = $tx->withdraw_value;
            $record->pending_withdraw_value = $tx->pending_withdraw_value;
            $record->cancel_withdraw_value = $tx->cancel_withdraw_value;

            $record->reported_at = $now;

            if (!$record->exists) {
                $record->created_at = $now;
            }

            $record->save();
            $count++;
        }

        return $count;
    }
}
