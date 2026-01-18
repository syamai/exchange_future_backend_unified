<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\OrderService;
use App\Jobs\ProcessOrderMETrade;
use App\Models\Order;
use App\Models\SpotCommands;
use App\Utils;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class KafkaMECommandReject extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka_me:command_reject';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command receive reject data command from ME';

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

        $topic = Consts::KAFKA_TOPIC_ME_COMMAND_REJECT;
        $handler = new HandlerMECommandReject();
        Utils::kafkaConsumerME($topic, $handler);

        return Command::SUCCESS;
    }

}

class HandlerMECommandReject {

    public function __invoke(KafkaConsumerMessage $message)
    {

        $data = Utils::convertDataKafka($message);
        try {
            $type = $data['type'];
            if (in_array($type, ['reject'])) {
                $orderId = $data['data']['orderId'];
                $userId = $data['data']['userId'];
                $status = 'pending';
				$commandKey = md5(json_encode($data));
				$command = SpotCommands::on('master')->where('command_key', $commandKey)->first();
				if ($command && $command->status != 'pending') {
					$command->delete();
					$command = null;
				}
				if(!$command) {
					$command = SpotCommands::create([
						'command_key' => $commandKey,
						'type_name' => $type,
						'user_id' => $userId,
						'obj_id' => $orderId,
						'payload' => json_encode($data),
						'status' => $status,

					]);

					if (!$command) {
						throw new Exception('can not create command');
					}

					$order = Order::find($orderId);
					if ($order->user_id != $userId) {
						$command->update(['status' => 'fail']);
						return;
					}

					ProcessOrderMETrade::onNewOrderTransactionRejected([
						'order_id' => $order->id,
						'command_id' => $command->id,
						'user_id' => $userId,
						'currency' => $order->currency,
						'coin' => $order->coin,
					]);
				}

                /*$orderService = new OrderService();
                //$orderService->cancel($userId, $orderId);



                if ($order->canCancel()) {
                    try {
                        DB::connection('master')->transaction(function () use (&$order, $orderService) {
                            $orderService->cancelOrder($order);
                        }, 3);
                        $command->update(['status' => 'success']);
                    } catch (\Exception $e) {
                        Log::error($e);
                        throw $e;
                    }
                }*/

            }
        } catch (Exception $e) {
            Log::error('Matching Engine Command Reject. Failed: '. json_encode($data));
            Log::error($e);
            throw $e;
        }

    }
}
