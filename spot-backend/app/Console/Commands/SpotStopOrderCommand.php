<?php

namespace App\Console\Commands;

use App\Jobs\SendOrderCreateToME;
use App\Utils;
use Illuminate\Support\Facades\App;
use App\Consts;
use App\Models\Order;
use App\Http\Services\OrderService;
use App\Http\Services\PriceService;
use App\Jobs\ProcessOrder;
use App\Utils\BigNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Services\HealthCheckService;
use Illuminate\Support\Facades\Log;
use Exception;

class SpotStopOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:trigger_stop_order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger Stop Order';


    protected int $processedId = 0;

    protected int $batchSize = 1000;

    protected int $interval = 5000; //milliseconds

    private PriceService $priceService;
    private OrderService $orderService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->priceService = new PriceService();
        $this->orderService = new OrderService();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        while (true) {
            $healthcheck = new HealthCheckService(
                Consts::HEALTH_CHECK_SERVICE_SPOT_STOP_ORDER,
                Consts::HEALTH_CHECK_DOMAIN_SPOT,
                false
            );
            $this->processedId = 0;
            $startTime = \App\Utils::currentMilliseconds();

            $healthcheck->serviceIsWorking();
            $this->doJob();

            if (App::runningUnitTests()) {
                break;
            }

            $sleepTime = $this->interval - (\App\Utils::currentMilliseconds() - $startTime);
            if ($sleepTime > 0) {
                usleep($sleepTime * 1000);
            }
        }
    }

    public function doJob()
    {
        do {
            $orders = $this->orderService->getStopOrdersInBatch($this->getProcessedId(), $this->batchSize);

            foreach ($orders as $order) {
                $this->processOrder($order);
            }
        } while (count($orders) === $this->batchSize);
    }

    private function processOrder($order)
    {
        $triggered = false;
        DB::transaction(function () use (&$order, &$triggered) {
            $triggered = $this->shouldTriggerOrder($order);
            if ($triggered) {
                $order = Order::lockForUpdate()->find($order->id);
                if ($order->isStoppingOrder()) {
                    $order = $this->orderService->activeStopOrder($order);
                    $this->orderService->sendUpdateOrderBookEvent(Consts::ORDER_BOOK_UPDATE_ACTIVATED, [$order]);
                }
            }

            $this->setProcessedId($order->id);
        });

        if ($triggered) {
            try {
                $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
                if ($matchingJavaAllow) {
                    //send kafka ME
                    SendOrderCreateToME::dispatchIfNeed($order->id);
                }
            } catch (Exception $ex) {
                Log::error($ex);
            }
            ProcessOrder::onNewOrderCreated($order);
        }
    }

    private function shouldTriggerOrder($order)
    {
        $triggerPrice = $this->getTriggerPrice($order);

        if ($order->stop_condition === Consts::ORDER_STOP_CONDITION_GE) {
            return BigNumber::new($triggerPrice)->comp($order->base_price) >= 0;
        } else {
            return BigNumber::new($triggerPrice)->comp($order->base_price) <= 0;
        }
    }

    private function getTriggerPrice($order)
    {
        $price = $this->priceService->getCurrentPrice($order->currency, $order->coin);
        return $price ? $price->price : 0;
    }

    private function getProcessedId(): int
    {
        return $this->processedId;
    }

    private function setProcessedId(int $id)
    {
        $this->processedId = $id;
    }
}
