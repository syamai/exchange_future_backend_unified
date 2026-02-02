<?php

namespace App\Services\Queue;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Redis Stream implementation of OrderQueueInterface (FR-IF-001)
 *
 * Uses Redis Streams with Consumer Groups for reliable message processing.
 */
class RedisStreamQueue implements OrderQueueInterface
{
    private const DEFAULT_STREAM = 'matching-engine:orders';
    private const DEFAULT_GROUP = 'matching-engine-group';
    private const MAX_PENDING_AGE_MS = 60000; // FR-DS-003

    private string $streamKey;
    private string $groupName;
    private string $consumerId;
    private DeadLetterQueue $dlq;
    private bool $groupCreated = false;

    public function __construct(
        ?string $streamKey = null,
        ?string $groupName = null,
        ?string $consumerId = null
    ) {
        $this->streamKey = $streamKey ?? self::DEFAULT_STREAM;
        $this->groupName = $groupName ?? self::DEFAULT_GROUP;
        $this->consumerId = $consumerId ?? gethostname() . '-' . getmypid();
        $this->dlq = new DeadLetterQueue();

        $this->ensureConsumerGroup();
    }

    /**
     * Push an order to the queue.
     */
    public function push(array $order): string
    {
        try {
            $messageId = Redis::xadd($this->streamKey, '*', [
                'data' => json_encode($order),
                'timestamp' => microtime(true),
            ]);

            return $messageId;
        } catch (\Exception $e) {
            Log::error("RedisStreamQueue: Failed to push order", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Pop orders from the queue using consumer group.
     */
    public function pop(int $batchSize = 10, int $blockMs = 1000): array
    {
        $this->ensureConsumerGroup();

        try {
            // First, try to claim old pending messages
            $claimed = $this->claimOldPending($batchSize);
            if (!empty($claimed)) {
                return $claimed;
            }

            // Read new messages
            $result = Redis::xreadgroup(
                $this->groupName,
                $this->consumerId,
                [$this->streamKey => '>'],
                $batchSize,
                $blockMs
            );

            if (empty($result)) {
                return [];
            }

            return $this->parseMessages($result[$this->streamKey] ?? []);
        } catch (\Exception $e) {
            Log::error("RedisStreamQueue: Failed to pop orders", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Acknowledge successful processing.
     */
    public function ack(string $messageId): void
    {
        try {
            Redis::xack($this->streamKey, $this->groupName, $messageId);
        } catch (\Exception $e) {
            Log::error("RedisStreamQueue: Failed to ack message", [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Negative acknowledge - increment retry counter.
     */
    public function nack(string $messageId): void
    {
        $retryCount = $this->dlq->incrementRetry($messageId);

        Log::warning("RedisStreamQueue: Message nack'd", [
            'message_id' => $messageId,
            'retry_count' => $retryCount,
        ]);
    }

    /**
     * Send message to dead letter queue.
     */
    public function sendToDLQ(string $messageId, array $data, string $reason): void
    {
        $this->dlq->send($messageId, $data, $reason);
        $this->ack($messageId); // Remove from main stream
    }

    /**
     * Get queue statistics.
     */
    public function getStats(): array
    {
        try {
            $length = Redis::xlen($this->streamKey);
            $pending = Redis::xpending($this->streamKey, $this->groupName);
            $dlqStats = $this->dlq->getStats();

            return [
                'stream_length' => $length,
                'pending_count' => $pending[0] ?? 0,
                'consumer_id' => $this->consumerId,
                'dlq' => $dlqStats,
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if queue is healthy.
     */
    public function isHealthy(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ensure consumer group exists.
     */
    private function ensureConsumerGroup(): void
    {
        if ($this->groupCreated) {
            return;
        }

        try {
            Redis::xgroup('CREATE', $this->streamKey, $this->groupName, '0', 'MKSTREAM');
            $this->groupCreated = true;
        } catch (\Exception $e) {
            // Group already exists
            if (str_contains($e->getMessage(), 'BUSYGROUP')) {
                $this->groupCreated = true;
                return;
            }
            throw $e;
        }
    }

    /**
     * Claim old pending messages from crashed consumers.
     */
    private function claimOldPending(int $count): array
    {
        try {
            // Get old pending messages
            $pending = Redis::xpending(
                $this->streamKey,
                $this->groupName,
                '-',
                '+',
                $count
            );

            if (empty($pending)) {
                return [];
            }

            $oldMessageIds = [];
            foreach ($pending as $entry) {
                $idleTime = $entry[2] ?? 0; // Idle time in ms
                if ($idleTime >= self::MAX_PENDING_AGE_MS) {
                    $oldMessageIds[] = $entry[0];
                }
            }

            if (empty($oldMessageIds)) {
                return [];
            }

            // Claim old messages
            $claimed = Redis::xclaim(
                $this->streamKey,
                $this->groupName,
                $this->consumerId,
                self::MAX_PENDING_AGE_MS,
                $oldMessageIds
            );

            return $this->parseMessages($claimed);
        } catch (\Exception $e) {
            Log::warning("RedisStreamQueue: Failed to claim pending", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Parse Redis stream messages into order data.
     */
    private function parseMessages(array $messages): array
    {
        $result = [];

        foreach ($messages as $messageId => $data) {
            if (isset($data['data'])) {
                $orderData = json_decode($data['data'], true);
                if ($orderData) {
                    $result[$messageId] = $orderData;
                }
            }
        }

        return $result;
    }

    /**
     * Get dead letter queue instance.
     */
    public function getDLQ(): DeadLetterQueue
    {
        return $this->dlq;
    }
}
