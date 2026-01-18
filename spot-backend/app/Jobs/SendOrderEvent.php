<?php

namespace App\Jobs;

use App\Consts;
use App\Utils;
use App\Events\OrderChanged;
use App\Models\Order;
use App\Http\Services\MasterdataService;
use App\Http\Services\OrderService;
use App\Http\Services\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendOrderEvent extends RedisQueueJob
{
    private $userId;
    private $order;
    private $action;
    private $message;

    private $orderService;
    private $userService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($json)
    {
        $data = json_decode($json);
        $this->userId = $data[0];
        $this->order = $data[1];
        $this->action = $data[2];
        $this->message = $data[3];

        $this->orderService = new OrderService();
        $this->userService = new UserService();
    }

    public static function getUniqueKey()
    {
        $params = func_get_args();
        $userId = $params[0];
        $order = $params[1];
        $action = $params[2];
        if ($order) {
            return implode('_', [$userId, $order->id, $action]);
        } else {
            return implode('_', [$userId, $action]);
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $settings = $this->userService->getOrderBookSettings($this->userId, Consts::CURRENCY_USD, Consts::CURRENCY_BTC);
        if ($settings->notification) {
            if (($this->action == Consts::ORDER_EVENT_CREATED && $settings->notification_created)) {
                $this->sendEvent();
            } elseif (($this->action == Consts::ORDER_EVENT_MATCHED && $settings->notification_matched)
                || ($this->action == Consts::ORDER_EVENT_CANCELED && $settings->notification_canceled)) {
                event(new OrderChanged($this->userId, $this->order, $this->action, $this->message));
            }
        }
    }

    private function sendEvent()
    {
        event(new OrderChanged($this->userId, $this->order, $this->action, $this->message));
    }
}
