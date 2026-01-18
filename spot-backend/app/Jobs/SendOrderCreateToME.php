<?php

namespace App\Jobs;

use App\Http\Services\MasterdataService;
use App\Models\Order;
use App\Models\SpotCommands;
use App\Consts;
use App\Utils;
use App\Utils\BigNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Exception;

class SendOrderCreateToME implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $orderId = null;

    /**
     * Create a new job instance.
     * @param $data
     */
    public function __construct($orderId)
    {
        $this->onConnection(Consts::CONNECTION_RABBITMQ);
        $this->orderId = $orderId;
    }

    public static function dispatchIfNeed($orderId)
    {
        return SendOrderCreateToME::dispatch($orderId)->onQueue(Consts::QUEUE_NEW_ORDER_ME);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            logger()->info('Send New Order to ME Request ==============' . json_encode($this->orderId));
            $order = Order::find($this->orderId);
            if ($order && $order->canMatching()) {
                //echo "\n\t\r order: {$this->orderId}";
                $currencyCoins = MasterdataService::getOneTable('coin_settings');
                $pairInfo = $currencyCoins->filter(function ($item) use ($order) {
                    return $item->coin == $order->coin && $item->currency == $order->currency;
                })->first();
                $pricePrecision =  1;
                $quantityPrecision = 1;
                if ($pairInfo) {
                    $pricePrecision = $pairInfo->price_precision;
                    $quantityPrecision = $pairInfo->quantity_precision;
                }

                $dataOrder = [
                    'type' => "order",
                    'data' => [
                        'orderId' => $order->id,
                        'userId' => $order->user_id,
                        'currency' => $order->currency,
                        'coin' => $order->coin,
                        'tradeType' => $order->trade_type,
                        'type' => $order->type,
                        'price' => BigNumber::round(BigNumber::new($order->price)->div($pricePrecision), BigNumber::ROUND_MODE_HALF_UP, 0),
                        'quantity' => BigNumber::round(BigNumber::new($order->quantity)->sub(BigNumber::new($order->executed_quantity))->div($quantityPrecision), BigNumber::ROUND_MODE_HALF_UP, 0),
                    ]
                ];

                $commandKey = md5(json_encode($dataOrder));
                $command = SpotCommands::on('master')->where('command_key', $commandKey)->first();
                if (!$command) {
                    $command = SpotCommands::create([
                        'command_key' => $commandKey,
                        'type_name' => 'order',
                        'user_id' => $order->user_id,
                        'obj_id' => $order->id,
                        'payload' => json_encode($dataOrder)

                    ]);
                    if (!$command) {
                        throw new Exception('can not create command');
                    }
                    $dataOrder['data']['commandId'] = $command->id;
                    Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_COMMAND, $dataOrder);
                }  else {
                    logger()->error("Dup send kafka new order ======== " . $this->orderId);
                }
            }

        } catch (\Exception $e) {
            logger()->error("REQUEST SEND New ORDER TO ME FAIL ======== " . $e->getMessage());
            throw $e;
        }
    }
}
