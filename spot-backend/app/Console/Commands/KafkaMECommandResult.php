<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\OrderService;
use App\Jobs\ProcessSpotCommandResult;
use App\Models\Order;
use App\Models\SpotCommands;
use App\Utils;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class KafkaMECommandResult extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka_me:command_result';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command receive result data command from ME';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
        if (!$matchingJavaAllow) {
            return Command::SUCCESS;
        }

        $topic = Consts::KAFKA_TOPIC_ME_COMMAND_RESULT;
        $handler = new HandlerMECommandResult();
        Utils::kafkaConsumerME($topic, $handler);

        return Command::SUCCESS;
    }

}

class HandlerMECommandResult {

    public function __invoke(KafkaConsumerMessage $message)
    {

        $data = Utils::convertDataKafka($message);
        try {
//            $type = $data['type'];
//            $commandId = isset($data['data']['commandId']) ? $data['data']['commandId'] : 0;
//            $status = $data['data']['result'];
//
//            $command = null;
//            if ($commandId) {
//                $command = SpotCommands::find($commandId);
//            } elseif (in_array($type, ['order', 'cancel'])) {
//                $orderId = $data['data']['orderId'];
//                $userId = $data['data']['userId'];
//                $command = SpotCommands::where(
//                    [
//                        'user_id' => $userId,
//                        'type_name' => $type,
//                        'obj_id' => $orderId
//                    ])
//                    ->first();
//
//                if ($type == "order" && $status == "fail") {
//                    $orderService = new OrderService();
//                    //$orderService->cancel($userId, $orderId);
//                    $order = Order::find($orderId);
//                    if ($order->user_id != $userId) {
//                        $command->update(['status' => 'fail']);
//                        return;
//                    }
//
//                    if ($order->canCancel()) {
//                        try {
//                            DB::connection('master')->transaction(function () use (&$order, $orderService) {
//                                $orderService->cancelOrder($order);
//                            }, 3);
//                        } catch (\Exception $e) {
//                            Log::error($e);
//                            throw $e;
//                        }
//                    }
//                } elseif($type == "cancel" && $status == "success") {
//                    $orderService = new OrderService();
//                    $order = Order::find($orderId);
//                    if ($order->canCancel()) {
//                        try {
//                            DB::connection('master')->transaction(function () use (&$order, $orderService) {
//                                $orderService->cancelOrder($order);
//                            }, 3);
//                        } catch (\Exception $e) {
//                            $status = "fail";
//                        }
//                    }
//                }
//            }
//            if (!$command) {
//                throw new Exception('Not found command id in message from ME.');
//            }
//
//            $command->update([
//                'status' => $status,
//                'payload_result' => json_encode($data)
//            ]);
            ProcessSpotCommandResult::onNewOrderTransactionCreated($data);

            //throw new Exception('This application must be run on the command line.');
        } catch (Exception $e) {
            Log::error('Matching Engine Command Result. Failed: '. json_encode($data));
            Log::error($e);
            throw $e;
        }

    }
}
