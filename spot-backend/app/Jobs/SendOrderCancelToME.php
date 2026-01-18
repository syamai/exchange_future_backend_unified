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

class SendOrderCancelToME implements ShouldQueue
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
        return SendOrderCancelToME::dispatch($orderId)->onQueue(Consts::QUEUE_CANCEL_ORDER_ME);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            logger()->info('Send Cancel Order to ME Request ==============' . json_encode($this->orderId));
            $order = Order::find($this->orderId);
            if ($order && $order->canMatching()) {
                //send kafka ME
                $dataOrder = [
                    'type' => 'cancel',
                    'data' => [
                        'orderId' => $order->id,
                        'userId' => $order->user_id,
                        'currency' => $order->currency,
                        'coin' => $order->coin,
                    ]
                ];

                $commandKey = md5(json_encode($dataOrder));
                $command = SpotCommands::on('master')->where('command_key', $commandKey)->first();
                if ($command && $command->status != 'pending') {
                    $command->delete();
                    $command = null;
                }

                if(!$command) {
                    $command = SpotCommands::create([
                        'command_key' => $commandKey,
                        'type_name' => 'cancel',
                        'user_id' => $order->user_id,
                        'obj_id' => $order->id,
                        'payload' => json_encode($dataOrder)

                    ]);
                    if (!$command) {
                        throw new Exception('can not create command');
                    }
                    $dataOrder['data']['commandId'] = $command->id;
                    Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_COMMAND, $dataOrder);
                }
            }

        } catch (\Exception $e) {
            logger()->error("REQUEST Cancel ORDER TO ME FAIL ======== " . $e->getMessage());
            throw $e;
        }
    }
}
