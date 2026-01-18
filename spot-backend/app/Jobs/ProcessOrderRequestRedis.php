<?php

namespace App\Jobs;

use App\Consts;
use App\Http\Services\MasterdataService;
use App\Models\SpotCommands;
use App\Utils;
use App\Models\Order;
use App\Http\Services\OrderService;
use App\Http\Services\PriceService;
use App\Utils\BigNumber;
use App\Utils\RedisLock;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Http\Services\HealthCheckService;

class ProcessOrderRequestRedis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const ORDER_REQUEST_ACTION_ADD = 'add';
	const ORDER_REQUEST_ACTION_CANCELED = 'cancel';

    private $orderService;
    private $priceService;

    protected $currency;
    protected $coin;

    protected $redis;

    protected $lastRun;
    protected $lastMatchingSuccess;
    protected $checkingInterval;

    protected $healthCheck;
    protected $orderLoss;
    protected $countMatch;
    protected $processOrderPrefix;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($currency, $coin)
    {
        $this->currency = $currency;
        $this->coin = $coin;
        $this->orderService = new OrderService();
        $this->priceService = new PriceService();
        $this->lastMatchingSuccess = true;
        $this->checkingInterval = env('OP_CHECKING_INTERVAl', 3000);
        $this->orderLoss = [];
		$this->processOrderPrefix = env('PROCESS_ORDER_REQUEST_REDIS_PREFIX', '');
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
                $this->lastRun = Utils::currentMilliseconds();
                $this->redis->set($this->getLastRunKey(), $this->lastRun);
            }

            if ($this->processNextUnprocessedTransOrder()) {
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
        return 'process_order_request_' . $this->processOrderPrefix . $this->currency . '_' . $this->coin;
    }

	/**
	 * @param $dataInfo
	 * @throws Exception
	 */
	public static function onNewOrderRequestCreated($dataInfo)
    {
        static::addToUnprocessedQueue($dataInfo, self::ORDER_REQUEST_ACTION_ADD);
    }

	public static function onNewOrderRequestCanceled($dataInfo)
	{
		static::addToUnprocessedQueue($dataInfo, self::ORDER_REQUEST_ACTION_CANCELED);
	}

    static function addToUnprocessedQueue($dataInfo, $action)
    {
        $redis = Redis::connection(static::getRedisConnection());
        if ($action === self::ORDER_REQUEST_ACTION_ADD || $action === self::ORDER_REQUEST_ACTION_CANCELED) {
			$time = Utils::currentMilliseconds();
        } else {
            throw new Exception("Invalid action $action");
        }
        $currency = strtolower($dataInfo['currency']);
        $coin = strtolower($dataInfo['coin']);
        $queueName = static::getUnprocessedQueueName($currency, $coin);

        $dataInfo['action'] = $action;
        $data = json_encode($dataInfo);
        $redis->zadd($queueName, $time, $data);
    }

    private function getNextUnprocessedTransOrder()
    {
        $queueName = $this->getUnprocessedQueueName($this->currency, $this->coin);
        while (true) {
            $result = $this->redis->zrange($queueName, 0, 0);
            if (empty($result)) {
                return null;
            }
            $this->redis->zrem($queueName, $result[0]);
            $data = json_decode($result[0], true);
            if (isset($data['action'])) {
                if (in_array($data['action'],[self::ORDER_REQUEST_ACTION_ADD, self::ORDER_REQUEST_ACTION_CANCELED])) {
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
	private function processNextUnprocessedTransOrder(): bool
	{
        $data = $this->getNextUnprocessedTransOrder();

        if (!$data) {
            return false;
        }
        $action = $data['action'];

        if ($action === self::ORDER_REQUEST_ACTION_ADD) {
			$this->createOrder($data);
		} elseif ($action === self::ORDER_REQUEST_ACTION_CANCELED) {
			$this->cancelOrder($data);
        } else {
            throw new Exception("Invalid action $action");
        }
        return true;
    }

    private static function getUnprocessedQueueName($currency, $coin): string
    {
		$processOrderPrefix = env('PROCESS_ORDER_REQUEST_REDIS_PREFIX', '');
        return 'un_processed_order_request_' . $processOrderPrefix. $currency . '_' . $coin;
    }

    public function createOrder($data)
    {
    	$logTest = env('LOG_PROCESS_ORDER_REQUEST', false);
		$timeStart = microtime(true);
		if ($logTest) {
			echo "\nStart: ".$timeStart;
		}

        try {
			$order = null;
			$orderId = $data['orderId'];
			if ($logTest) {
				echo "\nProccess OrderId: ".$orderId;
			}
			DB::connection('master')->transaction(function () use ($orderId, &$order) {
				$order = Order::on('master')->sharedLock()->find($orderId);
				if (!$order) {
					logger("Invalid order id ({$orderId})");
					return;
				}
				if ($order->status !== Consts::ORDER_STATUS_NEW) {
					logger("Invalid order status ({$order->id}, {$order->status})");
					return;
				}
				$order->status = $this->getOrderStatus($order);
				if (!$this->orderService->updateBalanceForNewOrder($order)) {
					$order->status = Consts::ORDER_STATUS_CANCELED;
				}
				$order->save();
			}, 3);

			if ($logTest) {
				$timeEnd = microtime(true);
				echo "\nEnd UpdateBalance:" . ($timeEnd - $timeStart);
				$timeStart = microtime(true);
			}

			if ($order && $order->canMatching()) {
				$this->orderService->sendUpdateOrderBookEvent(Consts::ORDER_BOOK_UPDATE_CREATED, [$order]);
				if ($logTest) {
					$timeEnd = microtime(true);
					echo "\nEnd sendUpdateOrderBookEvent:" . ($timeEnd - $timeStart);
					$timeStart = microtime(true);
				}

				$matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
				if ($matchingJavaAllow) {
					//send kafka ME
					//SendOrderCreateToME::dispatchIfNeed($order->id);
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
						logger()->error("Dup send kafka new order ======== " . $orderId);
					}
				} else {
					ProcessOrder::onNewOrderCreated($order);
				}
				if ($logTest) {
					$timeEnd = microtime(true);
					echo "\nEnd kafkaProducerME:" . ($timeEnd - $timeStart);
					echo "\n";
				}

			}
        } catch (Exception $e) {
            Log::error('Process order request redis. Failed to create order: ' . json_encode($data));
            Log::error($e);
            //throw $e;
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

	private function cancelOrder($data)
	{
		try {
			$order = null;
			$orderId = $data['orderId'];
			$sendKafka = false;
			DB::connection('master')->transaction(function () use ($orderId, &$order, &$sendKafka) {
				$order = Order::on('master')->sharedLock()->find($orderId);

				if (!$order) {
					logger("Invalid order status ({$orderId})");
					return;
				}

				if (!$order->canCancel()) {
					logger("Invalid order status ({$order->id}, {$order->status})");
					return;
				}

				$matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
				if (!$matchingJavaAllow || $order->status == Consts::ORDER_STATUS_STOPPING || $order->status == Consts::ORDER_STATUS_NEW) {
					$this->orderService->cancelOrder($order);
					ProcessOrder::onOrderCanceled($order);
				} else {
					//SendOrderCancelToME::dispatchIfNeed($order->id);
					$sendKafka = true;
				}
			}, 3);

			if ($sendKafka && $order) {
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
			$this->orderService->sendOrderChangedEvent(Consts::ORDER_EVENT_CANCELED, [$order]);

		} catch (Exception $ex) {
			Log::error('Process order request redis. Failed Canceled: '. json_encode($data));
			Log::error($ex);
			throw $ex;
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
