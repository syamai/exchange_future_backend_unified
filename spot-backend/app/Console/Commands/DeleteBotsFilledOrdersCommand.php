<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Process;
use App\Models\User;
use App\Utils;
use Illuminate\Support\Facades\DB;

class DeleteBotsFilledOrdersCommand extends BaseLogCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:delete_bots_filled_orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete bots filled orders ';

    protected $process;

    protected int $batchSize = 100;
    protected $sleepTime = 200000;

    protected $bots;



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->process = Process::firstOrCreate(['key' => $this->getProcessKey()]);
        $this->doInitJob();

        while (true) {
            $this->doJob();
            usleep($this->sleepTime);
        }
    }

    protected function getProcessKey(): string
    {
        return 'delete_bots_filled_orders';
    }

    protected function log($message)
    {
        logger($message);
        $this->info($message);
    }

    protected function doInitJob()
    {
    }

    protected function doJob()
    {
        $count = 0;
        $shouldContinue = true;

        while ($shouldContinue) {
            if ($count > $this->batchSize) {
                $count = 0;
            }
            if ($count === 0) {
                $this->updateBotList();
            }
            $count++;


            $process = Process::find($this->process->id);
            $records = $this->getNextRecords($process);

            $deletingIds = [];

            $orderBuy = [];
            $orderSell = [];

            foreach ($records as $record) {
                $orderBuy[$record->buy_order_id] = $record->buy_order_id;
                $orderSell[$record->sell_order_id] = $record->sell_order_id;
                $process->processed_id = $record->id;
            }

            //get list order filled bot
            if ($orderBuy) {
                $orderBuyFilled = Order::where(['status' => Consts::ORDER_STATUS_EXECUTED])
                    ->whereIn('id', $orderBuy)
                    ->whereIn('user_id', $this->bots)
                    ->pluck('id')
                    ->toArray();

                if ($orderBuyFilled) {
                    //check order matching user
                    $orderBuyMatchingUsers = OrderTransaction::whereIn('buy_order_id', $orderBuyFilled)
                        ->whereNotIn('seller_id', $this->bots)
                        ->pluck('buy_order_id')
                        ->toArray();
                    $orderBuyDelete = array_diff($orderBuyFilled, $orderBuyMatchingUsers);
                    $deletingIds = array_merge($deletingIds, $orderBuyDelete);
                }
            }

            if ($orderSell) {
                $orderSellFilled = Order::where(['status' => Consts::ORDER_STATUS_EXECUTED])
                    ->whereIn('id', $orderSell)
                    ->whereIn('user_id', $this->bots)
                    ->pluck('id')
                    ->toArray();

                if ($orderSellFilled) {
                    //check order matching user
                    $orderSellMatchingUsers = OrderTransaction::whereIn('sell_order_id', $orderSellFilled)
                        ->whereNotIn('buyer_id', $this->bots)
                        ->pluck('sell_order_id')
                        ->toArray();
                    $orderSellDelete = array_diff($orderSellFilled, $orderSellMatchingUsers);
                    $deletingIds = array_merge($deletingIds, $orderSellDelete);
                }
            }

            if ($deletingIds) {
                Order::whereIn('id', $deletingIds)->delete();
                $this->log("Deleting filled order: " . json_encode($deletingIds));
            }


            // $this->log("Deleting order: " . json_encode($deletingIds));
            //Order::whereIn('id', $deletingIds)->delete();

            $process->save();
            $this->process = $process;

            usleep($this->sleepTime);
            $shouldContinue = count($records) === $this->batchSize;
        };
    }

    protected function updateBotList()
    {
        $this->bots = User::where('type', 'bot')->pluck('id')->toArray();
    }

    protected function getNextRecords($process)
    {
        $deleteTime = Utils::currentMilliseconds() - 43200000;
        return DB::table('order_transactions')
            ->where('id', '>', $process->processed_id)
            ->where('created_at', '<', $deleteTime)
            ->orderBy('id', 'asc')
            ->limit($this->batchSize)
            ->get();
    }

    protected function trackeeFilter($query)
    {
        return $query;
    }

    protected function shouldDeleteOrder($order)
    {
        return $order->isCanceled() && $this->isCreatedByBot($order);
    }

    protected function isCreatedByBot($order)
    {
        return $order->email && array_key_exists($order->email, $this->bots);
    }
}
