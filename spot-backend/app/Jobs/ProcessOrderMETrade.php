<?php

namespace App\Jobs;

use App\Consts;
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

class ProcessOrderMETrade implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const ORDER_TRANSACTION_ACTION_ADD = 'add';
	const ORDER_TRANSACTION_ACTION_REJECT = 'reject';
	const ORDER_TRANSACTION_ACTION_CANCELED = 'cancel';

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
		$timeSleep = env('PROCESS_ORDER_TIME_SLEEP_TRADE_SPOT', 200000); // 200ms
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

            usleep($timeSleep); // 200ms
        }

    }

    private function getLastRunKey(): string
    {
        return 'process_order_me_trade_' . $this->currency . '_' . $this->coin;
    }

	/**
	 * @throws Exception
	 */
	public static function onNewOrderTransactionCreated($dataInfo)
    {
        static::addToUnprocessedQueue($dataInfo, self::ORDER_TRANSACTION_ACTION_ADD);
    }

	public static function onNewOrderTransactionRejected($dataInfo)
	{
		static::addToUnprocessedQueue($dataInfo, self::ORDER_TRANSACTION_ACTION_REJECT);
	}

	public static function onNewOrderTransactionCanceled($dataInfo)
	{
		static::addToUnprocessedQueue($dataInfo, self::ORDER_TRANSACTION_ACTION_CANCELED);
	}

    static function addToUnprocessedQueue($dataInfo, $action)
    {
        $redis = Redis::connection(static::getRedisConnection());
        if ($action === self::ORDER_TRANSACTION_ACTION_ADD) {
            $time = $dataInfo['created_at'];
		} elseif ($action === self::ORDER_TRANSACTION_ACTION_REJECT || $action === self::ORDER_TRANSACTION_ACTION_CANCELED) {
			$time = Utils::currentMilliseconds();
        } else {
            throw new Exception("Invalid action $action");
        }
        $currency = strtolower($dataInfo['currency']);
        $coin = strtolower($dataInfo['coin']);
        $queueName = static::getUnprocessedQueueName($currency, $coin);
		if ($action === self::ORDER_TRANSACTION_ACTION_ADD) {
			static::slog("Add transaction order({$dataInfo['buy_order_id']} - {$dataInfo['sell_order_id']}), action $action to $queueName");
		}

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
                if (in_array($data['action'],[self::ORDER_TRANSACTION_ACTION_ADD,self::ORDER_TRANSACTION_ACTION_REJECT, self::ORDER_TRANSACTION_ACTION_CANCELED])) {
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

        if ($action === self::ORDER_TRANSACTION_ACTION_ADD) {
			$this->log("Process Trade order({$data['buy_order_id']} - {$data['sell_order_id']}), action $action");
			$this->addTransactionOrder($data);
		} elseif ($action === self::ORDER_TRANSACTION_ACTION_REJECT) {
        	$this->rejectOrder($data);
		} elseif ($action === self::ORDER_TRANSACTION_ACTION_CANCELED) {
			$this->cancelOrder($data);
        } else {
            throw new Exception("Invalid action $action");
        }
        return true;
    }

    private static function getUnprocessedQueueName($currency, $coin): string
    {
        return 'un_processed_order_me_trade_' . $currency . '_' . $coin;
    }

    public function addTransactionOrder($data)
    {
        try {
            $quantity = $data['quantity'];
            $price = $data['price'];
            $transactionType = $data['transaction_type'];
            //$createdAt = $data['created_at'];
            $buyOrderId = $data['buy_order_id'];
            $sellOrderId = $data['sell_order_id'];
            $buyFee = isset($data['buy_fee']) ? $data['buy_fee'] : 0;
            $sellFee = isset($data['sell_fee']) ? $data['sell_fee'] : 0;

            $isBuyerMaker = $transactionType != Consts::ORDER_TRADE_TYPE_BUY;

            $buyOrder = Order::on('master')->where('id', $buyOrderId)->first();
            $sellOrder = Order::on('master')->where('id', $sellOrderId)->first();

            if (!$buyOrder || !$sellOrder || !$buyOrder->canMatching() || !$sellOrder->canMatching()) {
                if (!$buyOrder || !$buyOrder->canMatching()) {
                    throw new Exception("Order not found: " . $buyOrderId);
                }
                if (!$sellOrder || !$sellOrder->canMatching()) {
                    throw new Exception("Order not found: " . $sellOrderId);
                }
            }

            $this->orderService->matchEngineOrders($buyOrder, $sellOrder, $price, $quantity, $buyFee, $sellFee, $isBuyerMaker);
        } catch (Exception $e) {
            Log::error('Matching Engine Trade. Failed to create complete_transactions: ' . json_encode($data));
            Log::error($e);
            //throw $e;
        }

    }

	private function rejectOrder($data)
	{
		try {
			$orderId = $data['order_id'];
			$commandId = $data['command_id'];
			$command = SpotCommands::on('master')->find($commandId);
			if ($command) {
				$order = Order::on('master')->find($orderId);
				if (!$order) {
					$command->update(['status' => 'fail']);
					return;
				}

				if ($order->canCancel()) {
					try {
						DB::connection('master')->transaction(function () use ($order) {
							$this->orderService->cancelOrder($order);
						}, 3);
						$command->update(['status' => 'success']);
					} catch (\Exception $e) {
						Log::error($e);
						throw $e;
					}
				}
			}
		} catch (Exception $ex) {
			Log::error('Matching Engine Command Reject. Failed: '. json_encode($data));
			Log::error($ex);
			throw $ex;
		}
	}

	private function cancelOrder($data)
	{
		try {
			$orderId = $data['order_id'];
			$commandId = $data['command_id'];
			$command = SpotCommands::on('master')->find($commandId);

			if ($command) {
				$order = Order::on('master')->find($orderId);
				if (!$order) {
					$command->update(['status' => 'fail']);
					return;
				}
				$status = $data['payload_result']['data']['result'];
				$message = isset($data['payload_result']['data']['message']) ? $data['payload_result']['data']['message'] : '';

				if ($status == "success" || $message == 'MATCHING_UNKNOWN_ORDER_ID') {
					if ($order->canCancel()) {
						try {
							DB::connection('master')->transaction(function () use ($order) {
								$this->orderService->cancelOrder($order);
							}, 3);
							$status = "success";
						} catch (\Exception $e) {
							Log::error($e);
							throw $e;
						}
					}
				}

				$command->update([
					'status' => $status,
					'payload_result' => json_encode($data['payload_result'])
				]);
			}
		} catch (Exception $ex) {
			Log::error('Matching Engine Command Canceled. Failed: '. json_encode($data));
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
