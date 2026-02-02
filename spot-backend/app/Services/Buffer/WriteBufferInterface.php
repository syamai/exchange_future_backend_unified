<?php

namespace App\Services\Buffer;

/**
 * Interface for database write buffering implementations.
 *
 * Abstracts write buffering strategy to enable:
 * - Synchronous writes (testing/debugging)
 * - Asynchronous batch writes (production)
 * - Memory-based buffering (Swoole coroutines)
 */
interface WriteBufferInterface
{
    /**
     * Add an order update to the buffer.
     *
     * @param int $orderId Order ID
     * @param array $data Order data to update
     * @return void
     */
    public function addOrder(int $orderId, array $data): void;

    /**
     * Add a trade to the buffer.
     *
     * @param array $data Trade data to insert
     * @return void
     */
    public function addTrade(array $data): void;

    /**
     * Add a balance update to the buffer.
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @param array $changes Balance changes (available_balance, total_balance)
     * @return void
     */
    public function addBalanceUpdate(int $userId, string $currency, array $changes): void;

    /**
     * Flush all buffered data to database immediately.
     *
     * @return FlushResult
     */
    public function flush(): FlushResult;

    /**
     * Check if buffer should be flushed based on size or time.
     *
     * @return bool
     */
    public function shouldFlush(): bool;

    /**
     * Get current buffer statistics.
     *
     * @return array
     */
    public function getStats(): array;

    /**
     * Clear all buffered data without writing to database.
     *
     * @return void
     */
    public function clear(): void;
}
