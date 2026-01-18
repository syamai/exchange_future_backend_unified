<?php

namespace App\Services;

use App\Consts;
use App\Http\Services\HealthCheckService;
use App\Http\Services\OrderService;
use App\Jobs\ProcessOrder;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Stream-based Matching Engine using Redis Streams.
 * Replaces polling with blocking reads for better performance.
 */
class StreamMatchingEngine
{
    private const STREAM_PREFIX = 'spot:orders:stream:';
    private const GROUP_NAME = 'matching-engine';
    private const BLOCK_MS = 1000;        // Block timeout for xreadgroup
    private const BATCH_SIZE = 10;        // Orders to read per batch
    private const MAX_PENDING_AGE = 60000; // Reclaim pending messages older than 60s

    private string $currency;
    private string $coin;
    private string $streamKey;
    private string $consumerId;

    private InMemoryOrderBook $orderBook;
    private OrderService $orderService;
    private $redis;
    private $healthCheck;

    private bool $running = false;
    private int $processedOrders = 0;
    private int $matchedPairs = 0;
    private int $errors = 0;

    public function __construct(string $currency, string $coin)
    {
        $this->currency = $currency;
        $this->coin = $coin;
        $this->streamKey = self::STREAM_PREFIX . $currency . ':' . $coin;
        $this->consumerId = 'consumer-' . gethostname() . '-' . getmypid();

        $this->orderBook = new InMemoryOrderBook($currency, $coin);
        $this->orderService = new OrderService();
        $this->redis = Redis::connection(Consts::RC_ORDER_PROCESSOR);
    }

    /**
     * Initialize the stream and consumer group.
     *
     * @return void
     */
    public function initialize(): void
    {
        // Create consumer group if not exists
        try {
            $this->redis->xgroup('CREATE', $this->streamKey, self::GROUP_NAME, '0', 'MKSTREAM');
            Log::info("StreamMatchingEngine: Created consumer group for {$this->streamKey}");
        } catch (Exception $e) {
            // Group already exists - this is fine
            if (strpos($e->getMessage(), 'BUSYGROUP') === false) {
                throw $e;
            }
        }

        // Load existing orders into memory
        $loaded = $this->orderBook->loadFromDatabase();
        Log::info("StreamMatchingEngine: Initialized with {$loaded} orders for {$this->coin}/{$this->currency}");
    }

    /**
     * Start the matching engine loop.
     *
     * @return void
     */
    public function run(): void
    {
        $this->running = true;
        $symbol = strtoupper($this->currency . $this->coin);
        $this->healthCheck = HealthCheckService::initForMatchingEngine(Consts::HEALTH_CHECK_DOMAIN_SPOT, $symbol);

        Log::info("StreamMatchingEngine: Starting for {$this->coin}/{$this->currency}");

        while ($this->running) {
            try {
                $this->healthCheck->matchingEngine();

                // First, reclaim any pending messages from dead consumers
                $this->reclaimPendingMessages();

                // Read new messages from stream (blocking)
                $messages = $this->readFromStream();

                if (!empty($messages)) {
                    $this->processMessages($messages);
                }

                // Perform matching
                $this->performMatching();

            } catch (Exception $e) {
                $this->errors++;
                Log::error("StreamMatchingEngine error: " . $e->getMessage());

                // Don't crash on transient errors
                if ($this->errors > 100) {
                    Log::critical("StreamMatchingEngine: Too many errors, stopping");
                    $this->running = false;
                }

                usleep(100000); // 100ms backoff on error
            }
        }

        Log::info("StreamMatchingEngine: Stopped for {$this->coin}/{$this->currency}");
    }

    /**
     * Stop the matching engine.
     *
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Read messages from Redis Stream.
     *
     * @return array
     */
    private function readFromStream(): array
    {
        $result = $this->redis->xreadgroup(
            self::GROUP_NAME,
            $this->consumerId,
            [$this->streamKey => '>'],
            self::BATCH_SIZE,
            self::BLOCK_MS
        );

        if (empty($result)) {
            return [];
        }

        return $result[$this->streamKey] ?? [];
    }

    /**
     * Reclaim pending messages from dead consumers.
     *
     * @return void
     */
    private function reclaimPendingMessages(): void
    {
        try {
            // Get pending messages older than MAX_PENDING_AGE
            $pending = $this->redis->xpending(
                $this->streamKey,
                self::GROUP_NAME,
                '-',
                '+',
                10
            );

            foreach ($pending as $entry) {
                $messageId = $entry[0];
                $idleTime = $entry[2];

                if ($idleTime > self::MAX_PENDING_AGE) {
                    // Reclaim the message
                    $claimed = $this->redis->xclaim(
                        $this->streamKey,
                        self::GROUP_NAME,
                        $this->consumerId,
                        self::MAX_PENDING_AGE,
                        [$messageId]
                    );

                    if (!empty($claimed)) {
                        Log::info("StreamMatchingEngine: Reclaimed message {$messageId}");
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore pending check errors
        }
    }

    /**
     * Process messages from the stream.
     *
     * @param array $messages
     * @return void
     */
    private function processMessages(array $messages): void
    {
        foreach ($messages as $messageId => $data) {
            try {
                $payload = json_decode($data['payload'] ?? '{}', true);
                $action = $payload['action'] ?? ProcessOrder::ORDER_ACTION_ADD;
                $orderId = $payload['id'] ?? null;

                if (!$orderId) {
                    $this->acknowledgeMessage($messageId);
                    continue;
                }

                if ($action === ProcessOrder::ORDER_ACTION_ADD) {
                    $order = Order::on('master')->find($orderId);
                    if ($order && $order->canMatching()) {
                        $this->orderBook->addOrder($order);
                        $this->processedOrders++;
                    }
                } elseif ($action === ProcessOrder::ORDER_ACTION_REMOVE) {
                    $this->orderBook->removeOrder($orderId);
                }

                $this->acknowledgeMessage($messageId);

            } catch (Exception $e) {
                Log::error("StreamMatchingEngine: Failed to process message {$messageId}: " . $e->getMessage());
                // Don't ack - will be retried or reclaimed
            }
        }
    }

    /**
     * Acknowledge a message as processed.
     *
     * @param string $messageId
     * @return void
     */
    private function acknowledgeMessage(string $messageId): void
    {
        $this->redis->xack($this->streamKey, self::GROUP_NAME, $messageId);
    }

    /**
     * Perform order matching using the in-memory orderbook.
     *
     * @return void
     */
    private function performMatching(): void
    {
        $matchCount = 0;
        $maxMatchesPerCycle = 50; // Prevent starvation of message reading

        while ($matchCount < $maxMatchesPerCycle) {
            $pair = $this->orderBook->getMatchablePair();

            if (!$pair) {
                break;
            }

            try {
                $this->executeMatch($pair['buy'], $pair['sell']);
                $matchCount++;
                $this->matchedPairs++;
            } catch (Exception $e) {
                Log::error("StreamMatchingEngine: Match failed: " . $e->getMessage());

                // Re-add orders to orderbook on failure
                $this->orderBook->addOrder($pair['buy']);
                $this->orderBook->addOrder($pair['sell']);
                break;
            }
        }
    }

    /**
     * Execute a match between buy and sell orders.
     *
     * @param Order $buyOrder
     * @param Order $sellOrder
     * @return void
     */
    private function executeMatch(Order $buyOrder, Order $sellOrder): void
    {
        DB::connection('master')->beginTransaction();

        try {
            // Refresh orders with lock
            $buyOrder = Order::on('master')->where('id', $buyOrder->id)->lockForUpdate()->first();
            $sellOrder = Order::on('master')->where('id', $sellOrder->id)->lockForUpdate()->first();

            if (!$buyOrder || !$sellOrder || !$buyOrder->canMatching() || !$sellOrder->canMatching()) {
                DB::connection('master')->rollBack();

                // Re-add valid orders back to orderbook
                if ($buyOrder && $buyOrder->canMatching()) {
                    $this->orderBook->addOrder($buyOrder);
                }
                if ($sellOrder && $sellOrder->canMatching()) {
                    $this->orderBook->addOrder($sellOrder);
                }
                return;
            }

            // Determine maker/taker
            $isBuyerMaker = $sellOrder->updated_at > $buyOrder->updated_at;

            // Execute match via OrderService
            $remaining = $this->orderService->matchOrders($buyOrder, $sellOrder, $isBuyerMaker);

            DB::connection('master')->commit();

            // Re-add remaining order if partially filled
            if ($remaining) {
                $this->orderBook->addOrder($remaining);
            }

            Log::debug("StreamMatchingEngine: Matched orders {$buyOrder->id} with {$sellOrder->id}");

        } catch (Exception $e) {
            DB::connection('master')->rollBack();
            throw $e;
        }
    }

    /**
     * Publish an order to the stream.
     *
     * @param Order $order
     * @param string $action
     * @return string Message ID
     */
    public static function publishOrder(Order $order, string $action = ProcessOrder::ORDER_ACTION_ADD): string
    {
        $streamKey = self::STREAM_PREFIX . $order->currency . ':' . $order->coin;
        $redis = Redis::connection(Consts::RC_ORDER_PROCESSOR);

        $payload = json_encode([
            'id' => $order->id,
            'action' => $action,
            'timestamp' => now()->timestamp,
        ]);

        return $redis->xadd($streamKey, '*', ['payload' => $payload]);
    }

    /**
     * Get engine statistics.
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'currency' => $this->currency,
            'coin' => $this->coin,
            'consumer_id' => $this->consumerId,
            'stream_key' => $this->streamKey,
            'running' => $this->running,
            'processed_orders' => $this->processedOrders,
            'matched_pairs' => $this->matchedPairs,
            'errors' => $this->errors,
            'orderbook' => $this->orderBook->getStats(),
        ];
    }

    /**
     * Get stream info.
     *
     * @return array
     */
    public function getStreamInfo(): array
    {
        try {
            $info = $this->redis->xinfo('STREAM', $this->streamKey);
            $groups = $this->redis->xinfo('GROUPS', $this->streamKey);

            return [
                'stream' => $info,
                'groups' => $groups,
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
