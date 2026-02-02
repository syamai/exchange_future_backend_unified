<?php

namespace App\Services\Queue;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Dead Letter Queue for failed order processing (FR-RL-003)
 *
 * Stores messages that failed processing after max retries,
 * allowing for manual review and reprocessing.
 */
class DeadLetterQueue
{
    // FR-RL-003 configuration
    private const MAX_RETRIES = 3;
    private const DLQ_KEY = 'matching-engine:dlq';
    private const RETRY_COUNT_PREFIX = 'matching-engine:retry:';
    private const DLQ_RETENTION_DAYS = 7;

    private string $dlqKey;

    public function __construct(?string $dlqKey = null)
    {
        $this->dlqKey = $dlqKey ?? self::DLQ_KEY;
    }

    /**
     * Increment retry count for a message.
     *
     * @param string $messageId
     * @return int Current retry count after increment
     */
    public function incrementRetry(string $messageId): int
    {
        $key = self::RETRY_COUNT_PREFIX . $messageId;
        $count = Redis::incr($key);

        // Set expiry on retry counter (1 hour)
        Redis::expire($key, 3600);

        return (int) $count;
    }

    /**
     * Get current retry count for a message.
     *
     * @param string $messageId
     * @return int
     */
    public function getRetryCount(string $messageId): int
    {
        return (int) (Redis::get(self::RETRY_COUNT_PREFIX . $messageId) ?? 0);
    }

    /**
     * Check if message should be moved to DLQ.
     *
     * @param string $messageId
     * @return bool
     */
    public function shouldMoveToDLQ(string $messageId): bool
    {
        return $this->getRetryCount($messageId) >= self::MAX_RETRIES;
    }

    /**
     * Send a failed message to the dead letter queue.
     *
     * @param string $messageId Original message ID
     * @param array $data Message data
     * @param string $reason Failure reason
     * @param \Exception|null $exception The exception that caused the failure
     * @return string DLQ entry ID
     */
    public function send(string $messageId, array $data, string $reason, ?\Exception $exception = null): string
    {
        $entry = [
            'original_id' => $messageId,
            'data' => json_encode($data),
            'reason' => $reason,
            'exception' => $exception ? [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ] : null,
            'retry_count' => $this->getRetryCount($messageId),
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
        ];

        try {
            $dlqId = Redis::xadd($this->dlqKey, '*', $entry);

            // Clean up retry counter
            Redis::del(self::RETRY_COUNT_PREFIX . $messageId);

            Log::warning("DeadLetterQueue: Message moved to DLQ", [
                'original_id' => $messageId,
                'dlq_id' => $dlqId,
                'reason' => $reason,
            ]);

            return $dlqId;
        } catch (\Exception $e) {
            Log::error("DeadLetterQueue: Failed to send to DLQ", [
                'original_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Read messages from the dead letter queue.
     *
     * @param int $count Number of messages to read
     * @param string $startId Start ID (use '0' for beginning, '-' for oldest)
     * @return array
     */
    public function read(int $count = 100, string $startId = '0'): array
    {
        try {
            $messages = Redis::xrange($this->dlqKey, $startId, '+', $count);

            return array_map(function ($id, $data) {
                $data['data'] = json_decode($data['data'] ?? '{}', true);
                $data['exception'] = isset($data['exception']) ?
                    json_decode($data['exception'], true) : null;
                return [
                    'id' => $id,
                    ...$data,
                ];
            }, array_keys($messages), $messages);
        } catch (\Exception $e) {
            Log::error("DeadLetterQueue: Failed to read from DLQ", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Reprocess a message from the dead letter queue.
     *
     * @param string $dlqId DLQ entry ID
     * @param callable $processor Function to process the message
     * @return bool Success status
     */
    public function reprocess(string $dlqId, callable $processor): bool
    {
        try {
            $messages = Redis::xrange($this->dlqKey, $dlqId, $dlqId);

            if (empty($messages)) {
                Log::warning("DeadLetterQueue: Message not found for reprocessing", [
                    'dlq_id' => $dlqId,
                ]);
                return false;
            }

            $data = $messages[$dlqId] ?? null;
            if (!$data) {
                return false;
            }

            $messageData = json_decode($data['data'] ?? '{}', true);

            // Process the message
            $processor($messageData);

            // Remove from DLQ on success
            Redis::xdel($this->dlqKey, $dlqId);

            Log::info("DeadLetterQueue: Message reprocessed successfully", [
                'dlq_id' => $dlqId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("DeadLetterQueue: Reprocessing failed", [
                'dlq_id' => $dlqId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete a message from the dead letter queue.
     *
     * @param string $dlqId
     * @return bool
     */
    public function delete(string $dlqId): bool
    {
        try {
            $deleted = Redis::xdel($this->dlqKey, $dlqId);
            return $deleted > 0;
        } catch (\Exception $e) {
            Log::error("DeadLetterQueue: Failed to delete from DLQ", [
                'dlq_id' => $dlqId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get dead letter queue statistics.
     *
     * @return array
     */
    public function getStats(): array
    {
        try {
            $length = Redis::xlen($this->dlqKey);
            $info = Redis::xinfo('STREAM', $this->dlqKey);

            return [
                'queue_length' => $length,
                'first_entry' => $info['first-entry'][0] ?? null,
                'last_entry' => $info['last-entry'][0] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'queue_length' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Trim old entries from the dead letter queue.
     *
     * @param int $maxAge Maximum age in seconds
     * @return int Number of entries removed
     */
    public function trim(?int $maxAge = null): int
    {
        $maxAge = $maxAge ?? (self::DLQ_RETENTION_DAYS * 86400);
        $cutoffTime = time() - $maxAge;

        $removed = 0;
        $messages = $this->read(1000);

        foreach ($messages as $message) {
            if (($message['timestamp'] ?? 0) < $cutoffTime) {
                if ($this->delete($message['id'])) {
                    $removed++;
                }
            }
        }

        if ($removed > 0) {
            Log::info("DeadLetterQueue: Trimmed old entries", [
                'removed' => $removed,
                'cutoff_age_days' => $maxAge / 86400,
            ]);
        }

        return $removed;
    }
}
