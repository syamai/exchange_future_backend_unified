<?php

namespace App\Jobs;

use App\Consts;
use App\Models\SpotCommands;
use App\Utils;
use App\Models\Order;
use App\Http\Services\OrderService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ProcessSpotCommandResult implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const ACTION_ADD = 'add';

    private $orderService;

    protected $type;
    protected $coin;
    protected $currency;

    protected $redis;

    protected $lastRun;
    protected $lastMatchingSuccess;
    protected $checkingInterval;

    protected $healthCheck;
    protected $orderLoss;
    protected $countMatch;

    /**
     * Create a new job instance.
     *
     * @param $type
     * @param string $coin
     * @param string $currency
     */
    public function __construct($type, $coin = '', $currency = '')
    {
        $this->type = $type;
        $this->coin = $coin;
        $this->currency = $currency;
        $this->orderService = new OrderService();
        $this->lastMatchingSuccess = true;
        $this->checkingInterval = env('OP_CHECKING_INTERVAl', 3000);
        $this->orderLoss = [];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->redis = Redis::connection(static::getRedisConnection());

        $this->lastRun = Utils::currentMilliseconds();
        while (true) {
            if ($this->lastRun + $this->checkingInterval - 500 < Utils::currentMilliseconds()) {
                // if last matching take more than 3s to finish
                // we need end this processor, because other processor has been started
                return;
            }
            if (Utils::currentMilliseconds() - $this->lastRun > $this->checkingInterval / 2) {
                //echo "\n\nkey: ".$this->getLastRunKey();
                $this->lastRun = Utils::currentMilliseconds();
                $this->redis->set($this->getLastRunKey(), $this->lastRun);
            }

            if ($this->processNextUnprocessedCommand()) {
                continue;
            }
            if (Utils::isTesting()) {
                break;
            }

            usleep(200000); // 200ms
        }

    }

    private function getLastRunKey(): string
    {
        if ($this->currency && $this->coin) {
            return 'process_spot_command_result_me_' . $this->type . '_' . $this->currency . '_' . $this->coin;
        }
        return 'process_spot_command_result_me_' . $this->type;
    }

	/**
	 * @throws Exception
	 */
	public static function onNewOrderTransactionCreated($dataInfo)
    {
        static::addToUnprocessedQueue($dataInfo, self::ACTION_ADD);
    }

    static function addToUnprocessedQueue($dataInfo, $action)
    {
        $redis = Redis::connection(static::getRedisConnection());
        if ($action === self::ACTION_ADD) {
            $time = Utils::currentMilliseconds();
        } else {
            throw new Exception("Invalid action $action");
        }
        $type = strtolower($dataInfo['type']);
        $coin = "";
        $currency = "";
        if ($type == 'cancel' || $type == 'order') {
            $coin = strtolower($dataInfo['data']['coin']);
            $currency = strtolower($dataInfo['data']['currency']);
        }

        $queueName = static::getUnprocessedQueueName($type, $coin, $currency);
        static::slog("Add transaction order({$dataInfo['type']}), action $action to $queueName");
        $dataInfo['action'] = $action;
        $data = json_encode($dataInfo);
        $redis->zadd($queueName, $time, $data);
    }

    private function getNextUnprocessedCommand()
    {
        $queueName = $this->getUnprocessedQueueName($this->type, $this->coin, $this->currency);
        while (true) {
            $result = $this->redis->zrange($queueName, 0, 0);
            if (empty($result)) {
                return null;
            }
            $this->redis->zrem($queueName, $result[0]);
            $data = json_decode($result[0], true);
            if (isset($data['action'])) {
                if ($data['action'] === self::ACTION_ADD) {

                    return $data;
                } else {
                    throw new Exception("Invalid action {$data['action']}");
                }
            }
        }
    }

	/**
	 * @throws Exception
	 */
	private function processNextUnprocessedCommand(): bool
	{
        $data = $this->getNextUnprocessedCommand();

        if (!$data) {
            return false;
        }
        $action = $data['action'];
        $this->log("Process Command Order({$data['type']}), action $action");

        if ($action === self::ACTION_ADD) {
            $this->addTransactionOrder($data);
        } else {
            throw new Exception("Invalid action $action");
        }
        return true;
    }

    private static function getUnprocessedQueueName($type, $coin = '', $currency = ''): string
    {
        $key = 'un_processed_spot_command_result_me_' . $type;
        if ($currency && $coin) {
            $key .= "_{$currency}_{$coin}";
        }
        return $key;
    }

    public function addTransactionOrder($data)
    {
        try {
            $type = $data['type'];
            $commandId = isset($data['data']['commandId']) ? $data['data']['commandId'] : 0;
            $status = $data['data']['result'];
            $message = isset($data['data']['message']) ? $data['data']['message'] : '';

            $command = null;
            if ($commandId) {
                $command = SpotCommands::on('master')->find($commandId);
            }

            if (in_array($type, ['order', 'cancel'])) {
                $orderId = $data['data']['orderId'];
                $userId = $data['data']['userId'];
                if (!$command) {
                    $command = SpotCommands::on('master')->where(
                        [
                            'user_id' => $userId,
                            'type_name' => $type,
                            'obj_id' => $orderId
                        ])
                        ->first();
                }

                if ($command && $type == "order" && $status == "fail") {
                    $orderService = new OrderService();
                    //$orderService->cancel($userId, $orderId);
                    $order = Order::on('master')->find($orderId);
                    if ($order->user_id != $userId) {
                        $command->update(['status' => 'fail']);
                        return;
                    }

                    if ($order->canCancel()) {
                        try {
                            DB::connection('master')->transaction(function () use (&$order) {
                                $this->orderService->cancelOrder($order);
                            }, 3);
                        } catch (\Exception $e) {
                            Log::error($e);
                            throw $e;
                        }
                    }
                } elseif($command && $type == "cancel"/* && ($status == "success" || $message == 'MATCHING_UNKNOWN_ORDER_ID')*/) {
                    $order = Order::on('master')->find($orderId);
					if (!$order) {
						$command->update(['status' => 'fail']);
						return;
					}

					/*if ($status != "success") {
						$deleteTime = Utils::currentMilliseconds() - 43200000;
						if ($order->updated_at <= $deleteTime) {
							$status = "success";
						}
					}
					if ($status == "success" && $order->canCancel()) {
						try {
							DB::connection('master')->transaction(function () use (&$order) {
								$this->orderService->cancelOrder($order);
							}, 3);
						} catch (\Exception $e) {
							$status = "fail";
						}
					}*/
					ProcessOrderMETrade::onNewOrderTransactionCanceled([
						'order_id' => $order->id,
						'command_id' => $command->id,
						'user_id' => $userId,
						'currency' => $order->currency,
						'coin' => $order->coin,
						'payload_result' => $data
					]);
					return true;
                }
            }

            if (!$command) {
                throw new Exception('Not found command id in message from ME.');
            }

            $command->update([
                'status' => $status,
                'payload_result' => json_encode($data)
            ]);

            //throw new Exception('This application must be run on the command line.');
        } catch (Exception $e) {
            Log::error('Matching Engine Command Result. Failed: '. json_encode($data));
            Log::error($e);
            //throw $e;
        }

    }

    private static function getRedisConnection()
    {
        return Consts::RC_ORDER_PROCESSOR;
    }

    private function log($message)
    {
        Log::info('==================== ' . $message);
    }

    static function slog($message)
    {
        Log::info('==================== ' . $message);
    }
}
