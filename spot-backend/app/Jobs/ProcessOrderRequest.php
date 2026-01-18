<?php

namespace App\Jobs;

use App\Consts;
use App\Http\Services\MasterdataService;
use App\Models\SpotCommands;
use App\Utils;
use App\Utils\BigNumber;
use App\Http\Services\OrderService;
use App\Http\Services\PriceService;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessOrderRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const CREATE = 'create';
    const CANCEL = 'cancel';

    private $id;
    private $action;
    private $orderService;
    private $priceService;

    /**
     * Create a new job instance.
     *
     * @param $id
     * @throws \Exception
     */
    public function __construct($id, $action)
    {
        $this->onConnection(Consts::CONNECTION_RABBITMQ);
        $this->onQueue(env('RABBITMQ_PREFIX') . 'order');
        $this->id = $id;
        $this->action = $action;
        $this->orderService = new OrderService();
        $this->priceService = new PriceService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            switch ($this->action) {
                case ProcessOrderRequest::CREATE:
                    $this->createOrder();
                    break;
                case ProcessOrderRequest::CANCEL:
                    $this->cancelOrder();
                    break;
                default:
                    logger("ProcessOrderRequest: invalid action: {$this->action}");
                    break;
            }
        } catch (\Exception $e) {
            Log::error($e);
//            exit(1);
        }
    }

    public function createOrder()
    {
        try {
            $order = null;
            DB::connection('master')->transaction(function () use (&$order) {
                $order = Order::on('master')->lockForUpdate()->find($this->id);
                if (!$order) {
                    logger("Invalid order id ({$this->id})");
                    return;
                }
                if ($order->status !== Consts::ORDER_STATUS_NEW) {
                    logger("Invalid order status ({$order->id}, {$order->status})");
                    return;
                }
                $order->status = $this->getOrderStatus($order);
                if ($this->orderService->updateBalanceForNewOrder($order)) {
                    $this->orderService->sendUpdateOrderBookEvent(Consts::ORDER_BOOK_UPDATE_CREATED, [$order]);
                } else {
                    $order->status = Consts::ORDER_STATUS_CANCELED;
                }
                $order->save();
            }, 3);

            if ($order && $order->canMatching()) {
                $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
                if ($matchingJavaAllow) {
                    //send kafka ME
                    SendOrderCreateToME::dispatchIfNeed($order->id);
                } else {
                    ProcessOrder::onNewOrderCreated($order);
                }

            }
        } catch (\Exception $e) {
            Log::error($e);
            throw $e;
        }
    }

    private function getOrderStatus($order): string
    {
        if ($order->type === Consts::ORDER_TYPE_LIMIT || $order->type === Consts::ORDER_TYPE_MARKET) {
            return Consts::ORDER_STATUS_PENDING;
        } else {
            $currentPrice = $this->priceService->getPrice($order->currency, $order->coin)->price;
            $basePrice = $order->base_price;
            $stopCondition = $order->stop_condition;
            if (($stopCondition == Consts::ORDER_STOP_CONDITION_GE && BigNumber::new($currentPrice)->comp($basePrice) >= 0)
                || ($stopCondition == Consts::ORDER_STOP_CONDITION_LE && BigNumber::new($currentPrice)->comp($basePrice) <= 0)) {
                return Consts::ORDER_STATUS_PENDING;
            } else {
                return Consts::ORDER_STATUS_STOPPING;
            }
        }
        throw new \Exception('Cannot determine order status');
    }


    public function cancelOrder()
    {
        try {
            $order = null;
            DB::connection('master')->transaction(function () use (&$order) {
                $order = Order::on('master')->lockForUpdate()->find($this->id);

                if (!$order) {
                    logger("Invalid order status ({$this->id})");
                    return;
                }

                if (!$order->canCancel()) {
                    logger("Invalid order status ({$order->id}, {$order->status})");
                    return;
                }
                
                $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
                if (!$matchingJavaAllow || $order->status == Consts::ORDER_STATUS_STOPPING) {
                    $this->orderService->cancelOrder($order);
                    ProcessOrder::onOrderCanceled($order);
                } else {
                    SendOrderCancelToME::dispatchIfNeed($order->id);
                }


                $this->orderService->sendOrderChangedEvent(Consts::ORDER_EVENT_CANCELED, [$order]);
            }, 3);
        } catch (\Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}
