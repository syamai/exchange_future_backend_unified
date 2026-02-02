<?php

namespace App\Services\Queue;

/**
 * Interface for order queue implementations (FR-IF-001)
 *
 * Abstracts the underlying queue mechanism to allow:
 * - Redis Stream implementation (current)
 * - Kafka implementation (future migration)
 * - In-memory implementation (testing)
 */
interface OrderQueueInterface
{
    /**
     * Push an order to the queue.
     *
     * @param array $order Order data
     * @return string Message ID
     */
    public function push(array $order): string;

    /**
     * Pop orders from the queue (batch read).
     *
     * @param int $batchSize Maximum number of orders to read
     * @param int $blockMs Blocking timeout in milliseconds (0 = non-blocking)
     * @return array Array of [messageId => orderData]
     */
    public function pop(int $batchSize = 10, int $blockMs = 1000): array;

    /**
     * Acknowledge successful processing of a message.
     *
     * @param string $messageId
     * @return void
     */
    public function ack(string $messageId): void;

    /**
     * Negative acknowledge - message processing failed.
     *
     * @param string $messageId
     * @return void
     */
    public function nack(string $messageId): void;

    /**
     * Send a message to the dead letter queue.
     *
     * @param string $messageId Original message ID
     * @param array $data Original message data
     * @param string $reason Failure reason
     * @return void
     */
    public function sendToDLQ(string $messageId, array $data, string $reason): void;

    /**
     * Get queue statistics.
     *
     * @return array
     */
    public function getStats(): array;

    /**
     * Check if queue is healthy/connected.
     *
     * @return bool
     */
    public function isHealthy(): bool;
}
