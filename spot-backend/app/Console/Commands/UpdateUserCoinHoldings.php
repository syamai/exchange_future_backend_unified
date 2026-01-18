<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\JobCheckpoints;
use App\Models\User;
use App\Models\UserCoinHoldings;
use App\Models\UserCoinHoldingsLogs;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateUserCoinHoldings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:user-holdings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update user coin holdings from executed orders';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $logTime = Carbon::now()->format('Y-m-d H:i:s');
        $this->info("{$logTime} START-[{$this->signature}]{$this->description} =====================\n");
        
        $jobName = "calculate_user_holdings";

        $now = round(microtime(true) * 1000);
        $chunkSize = config('constants.chunk_limit_checkpoint');
        // dd($chunkSize);

        try {
            $lastId = JobCheckpoints::jobName($jobName)->value('last_calculated_at') ?? 0;
            // Truy vấn orders: lấy cả order mới + order cũ có executed_quantity tăng thêm
            $orders = DB::table('orders as o')
                ->join('users as u', 'u.id', 'o.user_id')
                ->where('u.type', '<>', 'bot')
                ->whereIn('o.status', [Consts::ORDER_STATUS_EXECUTING, Consts::ORDER_STATUS_EXECUTED])
                ->where('o.currency', Consts::CURRENCY_USDT)
                ->where(function ($q) use ($lastId) {
                    $q->where('o.id', '>', $lastId)
                        ->orWhereRaw('
              o.executed_quantity > (
                  SELECT COALESCE(MAX(total_executed_after), 0)
                  FROM user_coin_holdings_logs
                  WHERE order_id = o.id
              )
          ');
                })
                ->select(
                    'o.id',
                    'o.user_id',
                    'o.coin',
                    'o.trade_type',
                    'o.executed_quantity',
                    'o.currency',
                    'o.type',
                    'o.fee',
                    'o.executed_price',
                    'o.base_price',
                    'o.stop_condition',
                    'o.reverse_price',
                    'o.market_type'
                )
                ->orderBy('o.id')
                ->limit($chunkSize)
                ->get();

            if ($orders->isEmpty()) {
                $this->info("{$logTime} END-[{$this->signature}] {$this->description} | No new orders to process. \n");
                return;
            }

            $this->processBatch($orders, $now);

            // Cập nhật checkpoint
            $newLastId = $orders->last()->id;

            JobCheckpoints::updateOrCreate(
                ['job' => $jobName],
                [
                    'last_calculated_at' => $newLastId,
                    'updated_at' => $now,
                ]
            );
        } catch (\Throwable $e) {
            Log::channel('spot_overview')->error('Error in [spot:user-holdings job', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $this->info("{$logTime} END-[{$this->signature}] {$this->description} ===== jobName:[{$jobName}] - {$newLastId}=====job completed===========\n");
    }

    protected function processBatch($orders, int $now): int
    {
        $count = 0;

        foreach ($orders as $order) {
            if (!$order->currency) {
                Log::channel('spot_overview')->debug('Missing currency on order', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'executed_quantity' => $order->executed_quantity,
                    'coin' => $order->coin,
                    'trade_type' => $order->trade_type,
                    'raw_data' => (array) $order,
                ]);
            }

            $loggedFee = DB::table('user_coin_holdings_logs')
                ->where('order_id', $order->id)
                ->sum('fee');

            $totalFeeNow = $order->fee ?? 0;
            $fee = max(0, $totalFeeNow - $loggedFee);

            $lastExecuted = DB::table('user_coin_holdings_logs')
                ->where('order_id', $order->id)
                ->max('total_executed_after') ?? 0;

            $delta = $order->executed_quantity - $lastExecuted;
            if ($delta <= 0) continue;

            UserCoinHoldingsLogs::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'currency' => $order->currency,
                'coin' => $order->coin,
                'order_type' => $order->type,
                'executed_price' => $order->executed_price,
                'base_price' => $order->base_price,
                'stop_condition' => $order->stop_condition,
                'reverse_price' => $order->reverse_price,
                'market_type' => $order->market_type,
                'fee' => $fee,
                'trade_type' => $order->trade_type,
                'executed_quantity_delta' => $delta,
                'total_executed_after' => $order->executed_quantity,
                'logged_at' => $now,
                'created_at' => $now,
            ]);

            $holdings = UserCoinHoldings::where('user_id', $order->user_id)
                ->where('coin', $order->coin)
                ->where('currency', $order->currency)
                ->first();

            $price = DB::table('prices')
                ->where('coin', $order->coin)
                ->where('currency', $order->currency)
                ->orderByDesc('created_at')
                ->value('price') ?? 0;

            $deltaValue = $delta * $price;

            $column = $order->trade_type === 'buy' ? 'total_buy' : 'total_sell';
            $valueColumn = $order->trade_type === 'buy' ? 'total_buy_value' : 'total_sell_value';

            if ($holdings) {
                $holdings->$column += $delta;
                $holdings->$valueColumn += $deltaValue;
                $holdings->total_fees_paid += $fee;
                $holdings->last_updated_at = $now;
                $holdings->save();
            } else {
                UserCoinHoldings::create([
                    'user_id' => $order->user_id,
                    'coin' => $order->coin,
                    'currency' => $order->currency,
                    'total_buy' => $column === 'total_buy' ? $delta : 0,
                    'total_sell' => $column === 'total_sell' ? $delta : 0,
                    'total_buy_value' => $column === 'total_buy' ? $deltaValue : 0,
                    'total_sell_value' => $column === 'total_sell' ? $deltaValue : 0,
                    'total_fees_paid' => $fee,
                    // 'created_at' => $now,
                    'last_updated_at' => $now,
                ]);
            }

            $count++;
        }

        return $count;
    }
}
