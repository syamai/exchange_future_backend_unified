<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\JobCheckpoints;
use App\Models\UserCoinPendings;
use App\Models\UserCoinPendingsLogs;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateUserCoinPendings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:user-pendings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'User pending position from orders';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $logTime = Carbon::now()->format('Y-m-d H:i:s');
        $this->info("{$logTime} START-[{$this->signature}] {$this->description} =====================\n");
        
        $count = 0;
        $newLastId = null;
        $jobName = 'calculate_user_coin_pending';
        $now = round(microtime(true) * 1000);
        $chunkSize = config('constants.chunk_limit_checkpoint');

        $this->info("Calculating pending positions (chunked {$chunkSize} rows)...");

        try {
            // Lấy checkpoint
            $lastProcessedId = JobCheckpoints::jobName($jobName)->value('last_calculated_at') ?? 0;

            // Truy vấn batch order
            $orders = DB::table('orders as o')
                ->join('users as u', 'u.id', 'o.user_id')
                ->where('u.type', '<>', 'bot')
                ->whereIn('o.status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
                ->whereRaw('o.quantity > o.executed_quantity')
                ->where(function ($q) use ($lastProcessedId) {
                    $q->where('o.id', '>', $lastProcessedId)
                        ->orWhereRaw('
                        o.quantity - o.executed_quantity > (
                            SELECT COALESCE(MAX(pending_quantity), 0)
                            FROM user_coin_pendings_logs
                            WHERE order_id = o.id
                        )
                    ');
                })
                ->select(
                    'o.id',
                    'o.user_id',
                    'o.coin',
                    'o.currency',
                    'o.trade_type',
                    'o.type as order_type',
                    'o.fee',
                    'o.executed_price',
                    'o.base_price',
                    'o.stop_condition',
                    'o.reverse_price',
                    'o.market_type',
                    DB::raw('o.quantity - o.executed_quantity as pending_quantity'),
                    DB::raw('(o.quantity - o.executed_quantity) * COALESCE(o.price, 0) as pending_value')
                )
                ->orderBy('o.id')
                ->limit($chunkSize)
                ->get();

            if ($orders->isEmpty()) {
                $this->info("{$logTime} END-[{$this->signature}] {$this->description} | No more pending orders to process. \n");
                return;
            }

            // Xử lý batch
            $count = $this->processBatch($orders, $now);

            // Lưu checkpoint
            $newLastId = $orders->last()->id;
            JobCheckpoints::updateOrCreate(
                ['job' => $jobName],
                [
                    'last_calculated_at' => $newLastId,
                    'updated_at' => $now,
                ]
            );
        } catch (\Throwable $e) {
            Log::channel('spot_overview')->error('Error in [spot:user-pendings] job', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $this->info("{$logTime} END-[{$this->signature}] {$this->description} ==== counts: [{$count}] | jobName:[{$jobName}] - {$newLastId}======job completed===========\n");
    }


    protected function processBatch($orders, int $now): int
    {
        $pendingMap = [];

        foreach ($orders as $order) {
            $key = "{$order->user_id}|{$order->coin}|{$order->currency}";

            if (!isset($pendingMap[$key])) {
                $pendingMap[$key] = [
                    'user_id' => $order->user_id,
                    'coin' => $order->coin,
                    'currency' => $order->currency,
                    'pending_buy_quantity' => 0,
                    'pending_sell_quantity' => 0,
                    'pending_buy_value' => 0,
                    'pending_sell_value' => 0,
                    'last_updated_at' => $now,
                ];
            }

            if ($order->trade_type === 'buy') {
                $pendingMap[$key]['pending_buy_quantity'] += $order->pending_quantity;
                $pendingMap[$key]['pending_buy_value'] += $order->pending_value;
            } else {
                $pendingMap[$key]['pending_sell_quantity'] += $order->pending_quantity;
                $pendingMap[$key]['pending_sell_value'] += $order->pending_value;
            }

            // Ghi log snapshot cho phân tích thống kê
            UserCoinPendingsLogs::create([
                'user_id'          => $order->user_id,
                'order_id'         => $order->id,
                'coin'             => $order->coin,
                'currency'         => $order->currency,
                'trade_type'       => $order->trade_type,
                'order_type'       => $order->order_type ?? '',
                'fee'              => $order->fee ?? 0,
                'executed_price'   => $order->executed_price ?? 0,
                'base_price'       => $order->base_price ?? 0,
                'pending_quantity' => $order->pending_quantity,
                'pending_value'    => $order->pending_value,
                'stop_condition'   => $order->stop_condition ?? '',
                'reverse_price'    => $order->reverse_price ?? 0,
                'market_type'      => $order->market_type ?? '',
                'logged_at'        => $now,
                // 'created_at'       => $now,
                // 'updated_at'       => $now,
            ]);
        }

        // Cập nhật bảng snapshot chính
        $count = 0;
        foreach ($pendingMap as $data) {
            $record = UserCoinPendings::where('user_id', $data['user_id'])
                ->where('coin', $data['coin'])
                ->where('currency', $data['currency'])
                ->first();

            if ($record) {
                $record->timestamps = false;
                $record->fill($data);
                $record->save();
            } else {
                UserCoinPendings::create($data + [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $count++;
        }

        return $count;
    }
}
