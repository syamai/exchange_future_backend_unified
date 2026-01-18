<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\OrderService;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpotCancelOrderBot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancel:order_bot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel all order bot';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // order
        $userIdAuto = env('FAKE_USER_AUTO_MATCHING', -1);

        $orders = Order::where('user_id', $userIdAuto)
            ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
            //->whereIn('type', [Consts::ORDER_TYPE_LIMIT, Consts::ORDER_TYPE_STOP_LIMIT])
            ->orderBy('updated_at', 'asc')
            ->get();
        if ($orders->isNotEmpty()) {
            $orderService = new OrderService();
            foreach ($orders as $order) {
                if ($order->canCancel()) {
                    try {
                        DB::connection('master')->transaction(function () use (&$order, $orderService) {
                            $orderService->cancelOrder($order);
                        }, 3);
                    } catch (\Exception $e) {
                        Log::error($e);
                        throw $e;
                    }
                }
            }
        }
    }
}
