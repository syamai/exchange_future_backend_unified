<?php

namespace App\Services\Buffer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Buffered database writer for high-performance matching engine.
 *
 * Implements the same pattern as future-backend's saveAccountsV2:
 * 1. Buffer updates in memory (Map/Array)
 * 2. Track updated entity IDs (Set)
 * 3. Periodically flush to DB as batch upsert
 * 4. Auto-retry on deadlock
 *
 * Performance impact:
 * - Before: 5-10ms per order (synchronous write) = 100-200 TPS
 * - After: 100 orders in 20ms batch = 0.2ms per order = 5,000 TPS
 */
class WriteBuffer implements WriteBufferInterface
{
    // Configuration
    private int $maxBufferSize;
    private int $flushIntervalMs;
    private int $maxRetries;

    // Order buffer: orderId => orderData
    private array $orderBuffer = [];

    // Trade buffer: sequential array of trade data
    private array $tradeBuffer = [];

    // Balance buffer: "userId:currency" => changes
    private array $balanceBuffer = [];

    // Timing
    private float $lastFlushTime;

    // Statistics
    private int $totalOrdersWritten = 0;
    private int $totalTradesWritten = 0;
    private int $totalBalancesWritten = 0;
    private int $totalFlushes = 0;
    private float $totalFlushDurationMs = 0.0;

    public function __construct(
        int $maxBufferSize = 100,
        int $flushIntervalMs = 500,
        int $maxRetries = 3
    ) {
        $this->maxBufferSize = $maxBufferSize;
        $this->flushIntervalMs = $flushIntervalMs;
        $this->maxRetries = $maxRetries;
        $this->lastFlushTime = microtime(true) * 1000;
    }

    /**
     * Add an order update to the buffer.
     *
     * If the same order is updated multiple times before flush,
     * only the latest update is kept (like future-backend's Map).
     */
    public function addOrder(int $orderId, array $data): void
    {
        // Merge with existing data if present (keep latest values)
        if (isset($this->orderBuffer[$orderId])) {
            $this->orderBuffer[$orderId] = array_merge(
                $this->orderBuffer[$orderId],
                $data
            );
        } else {
            $this->orderBuffer[$orderId] = array_merge(['id' => $orderId], $data);
        }
    }

    /**
     * Add a trade to the buffer.
     *
     * Trades are always inserted (not updated), so we use sequential array.
     */
    public function addTrade(array $data): void
    {
        $this->tradeBuffer[] = $data;
    }

    /**
     * Add a balance update to the buffer.
     *
     * Balance changes are merged by userId:currency key.
     * Multiple changes to the same account are combined.
     */
    public function addBalanceUpdate(int $userId, string $currency, array $changes): void
    {
        $key = "{$userId}:{$currency}";

        if (isset($this->balanceBuffer[$key])) {
            // Merge incremental changes
            foreach ($changes as $field => $value) {
                if (isset($this->balanceBuffer[$key][$field])) {
                    // Accumulate numeric changes
                    $this->balanceBuffer[$key][$field] = bcadd(
                        (string)$this->balanceBuffer[$key][$field],
                        (string)$value,
                        18
                    );
                } else {
                    $this->balanceBuffer[$key][$field] = $value;
                }
            }
        } else {
            $this->balanceBuffer[$key] = array_merge([
                'user_id' => $userId,
                'currency' => $currency,
            ], $changes);
        }
    }

    /**
     * Check if buffer should be flushed based on size or time.
     */
    public function shouldFlush(): bool
    {
        $totalSize = count($this->orderBuffer)
            + count($this->tradeBuffer)
            + count($this->balanceBuffer);

        if ($totalSize >= $this->maxBufferSize) {
            return true;
        }

        $timeSinceFlush = (microtime(true) * 1000) - $this->lastFlushTime;
        if ($timeSinceFlush >= $this->flushIntervalMs && $totalSize > 0) {
            return true;
        }

        return false;
    }

    /**
     * Flush all buffered data to database.
     *
     * Uses a single transaction with batch upsert operations.
     * Implements deadlock retry logic from future-backend.
     */
    public function flush(): FlushResult
    {
        if ($this->isEmpty()) {
            return FlushResult::empty();
        }

        $startTime = microtime(true);
        $errors = [];

        $ordersToWrite = $this->orderBuffer;
        $tradesToWrite = $this->tradeBuffer;
        $balancesToWrite = $this->balanceBuffer;

        // Clear buffers before write (prevents double-write on retry)
        $this->clear();

        $ordersWritten = 0;
        $tradesWritten = 0;
        $balancesWritten = 0;

        try {
            DB::connection('master')->transaction(function () use (
                $ordersToWrite,
                $tradesToWrite,
                $balancesToWrite,
                &$ordersWritten,
                &$tradesWritten,
                &$balancesWritten
            ) {
                // Batch update orders
                if (!empty($ordersToWrite)) {
                    $ordersWritten = $this->batchUpsertOrders($ordersToWrite);
                }

                // Batch insert trades
                if (!empty($tradesToWrite)) {
                    $tradesWritten = $this->batchInsertTrades($tradesToWrite);
                }

                // Batch update balances
                if (!empty($balancesToWrite)) {
                    $balancesWritten = $this->batchUpdateBalances($balancesToWrite);
                }
            });
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            // Retry on deadlock (like future-backend)
            if (strpos($errorMsg, 'Deadlock') !== false || strpos($errorMsg, 'ER_LOCK_DEADLOCK') !== false) {
                Log::warning("WriteBuffer: Deadlock detected, retrying...");
                return $this->retryFlush($ordersToWrite, $tradesToWrite, $balancesToWrite, 1);
            }

            Log::error("WriteBuffer flush failed: {$errorMsg}");
            $errors[] = $errorMsg;

            // Re-add failed data to buffer for next flush attempt
            $this->orderBuffer = array_merge($this->orderBuffer, $ordersToWrite);
            $this->tradeBuffer = array_merge($this->tradeBuffer, $tradesToWrite);
            $this->balanceBuffer = array_merge($this->balanceBuffer, $balancesToWrite);
        }

        $durationMs = (microtime(true) - $startTime) * 1000;
        $this->lastFlushTime = microtime(true) * 1000;

        // Update statistics
        $this->totalOrdersWritten += $ordersWritten;
        $this->totalTradesWritten += $tradesWritten;
        $this->totalBalancesWritten += $balancesWritten;
        $this->totalFlushes++;
        $this->totalFlushDurationMs += $durationMs;

        return new FlushResult(
            $ordersWritten,
            $tradesWritten,
            $balancesWritten,
            $durationMs,
            $errors
        );
    }

    /**
     * Retry flush on deadlock with exponential backoff.
     */
    private function retryFlush(
        array $ordersToWrite,
        array $tradesToWrite,
        array $balancesToWrite,
        int $attempt
    ): FlushResult {
        if ($attempt >= $this->maxRetries) {
            Log::error("WriteBuffer: Max retries exceeded for deadlock");
            // Re-add to buffer for next cycle
            $this->orderBuffer = array_merge($this->orderBuffer, $ordersToWrite);
            $this->tradeBuffer = array_merge($this->tradeBuffer, $tradesToWrite);
            $this->balanceBuffer = array_merge($this->balanceBuffer, $balancesToWrite);
            return FlushResult::failed("Deadlock retry limit exceeded");
        }

        // Exponential backoff: 10ms, 20ms, 40ms...
        usleep(10000 * pow(2, $attempt - 1));

        $startTime = microtime(true);
        $ordersWritten = 0;
        $tradesWritten = 0;
        $balancesWritten = 0;

        try {
            DB::connection('master')->transaction(function () use (
                $ordersToWrite,
                $tradesToWrite,
                $balancesToWrite,
                &$ordersWritten,
                &$tradesWritten,
                &$balancesWritten
            ) {
                if (!empty($ordersToWrite)) {
                    $ordersWritten = $this->batchUpsertOrders($ordersToWrite);
                }
                if (!empty($tradesToWrite)) {
                    $tradesWritten = $this->batchInsertTrades($tradesToWrite);
                }
                if (!empty($balancesToWrite)) {
                    $balancesWritten = $this->batchUpdateBalances($balancesToWrite);
                }
            });

            Log::info("WriteBuffer: Deadlock resolved after {$attempt} retries");
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Deadlock') !== false) {
                return $this->retryFlush($ordersToWrite, $tradesToWrite, $balancesToWrite, $attempt + 1);
            }
            throw $e;
        }

        $durationMs = (microtime(true) - $startTime) * 1000;
        return new FlushResult($ordersWritten, $tradesWritten, $balancesWritten, $durationMs, []);
    }

    /**
     * Batch upsert orders using MySQL's INSERT ... ON DUPLICATE KEY UPDATE.
     */
    private function batchUpsertOrders(array $orders): int
    {
        if (empty($orders)) {
            return 0;
        }

        $values = [];
        $bindings = [];

        foreach ($orders as $orderId => $data) {
            $values[] = '(?, ?, ?, ?, ?, ?)';
            $bindings[] = $orderId;
            $bindings[] = $data['status'] ?? null;
            $bindings[] = $data['executed_quantity'] ?? null;
            $bindings[] = $data['executed_price'] ?? null;
            $bindings[] = $data['fee'] ?? null;
            $bindings[] = $data['updated_at'] ?? now()->getTimestampMs();
        }

        $sql = "INSERT INTO orders (id, status, executed_quantity, executed_price, fee, updated_at)
                VALUES " . implode(', ', $values) . "
                ON DUPLICATE KEY UPDATE
                    status = COALESCE(VALUES(status), status),
                    executed_quantity = COALESCE(VALUES(executed_quantity), executed_quantity),
                    executed_price = COALESCE(VALUES(executed_price), executed_price),
                    fee = COALESCE(VALUES(fee), fee),
                    updated_at = VALUES(updated_at)";

        DB::connection('master')->statement($sql, $bindings);

        return count($orders);
    }

    /**
     * Batch insert trades using MySQL's multi-row INSERT.
     */
    private function batchInsertTrades(array $trades): int
    {
        if (empty($trades)) {
            return 0;
        }

        // Use Laravel's insert for simplicity and safety
        DB::connection('master')->table('order_transactions')->insert($trades);

        return count($trades);
    }

    /**
     * Batch update balances using optimized SQL.
     *
     * Note: Balance tables are currency-specific (spot_usd_accounts, spot_btc_accounts, etc.)
     */
    private function batchUpdateBalances(array $balances): int
    {
        if (empty($balances)) {
            return 0;
        }

        $written = 0;

        // Group by currency for table-specific updates
        $byCurrency = [];
        foreach ($balances as $key => $data) {
            $currency = $data['currency'];
            if (!isset($byCurrency[$currency])) {
                $byCurrency[$currency] = [];
            }
            $byCurrency[$currency][] = $data;
        }

        foreach ($byCurrency as $currency => $updates) {
            $tableName = "spot_{$currency}_accounts";

            foreach ($updates as $update) {
                $userId = $update['user_id'];
                $sets = [];
                $bindings = [];

                if (isset($update['available_balance'])) {
                    $sets[] = 'available_balance = available_balance + ?';
                    $bindings[] = $update['available_balance'];
                }

                if (isset($update['total_balance'])) {
                    $sets[] = 'total_balance = total_balance + ?';
                    $bindings[] = $update['total_balance'];
                }

                if (!empty($sets)) {
                    $bindings[] = $userId;
                    $sql = "UPDATE {$tableName} SET " . implode(', ', $sets) . " WHERE id = ?";
                    DB::connection('master')->statement($sql, $bindings);
                    $written++;
                }
            }
        }

        return $written;
    }

    /**
     * Check if buffer is empty.
     */
    private function isEmpty(): bool
    {
        return empty($this->orderBuffer)
            && empty($this->tradeBuffer)
            && empty($this->balanceBuffer);
    }

    /**
     * Clear all buffered data without writing.
     */
    public function clear(): void
    {
        $this->orderBuffer = [];
        $this->tradeBuffer = [];
        $this->balanceBuffer = [];
    }

    /**
     * Get current buffer statistics.
     */
    public function getStats(): array
    {
        $avgFlushDuration = $this->totalFlushes > 0
            ? $this->totalFlushDurationMs / $this->totalFlushes
            : 0;

        return [
            'buffer' => [
                'orders' => count($this->orderBuffer),
                'trades' => count($this->tradeBuffer),
                'balances' => count($this->balanceBuffer),
                'total' => count($this->orderBuffer) + count($this->tradeBuffer) + count($this->balanceBuffer),
            ],
            'config' => [
                'max_buffer_size' => $this->maxBufferSize,
                'flush_interval_ms' => $this->flushIntervalMs,
                'max_retries' => $this->maxRetries,
            ],
            'totals' => [
                'orders_written' => $this->totalOrdersWritten,
                'trades_written' => $this->totalTradesWritten,
                'balances_written' => $this->totalBalancesWritten,
                'flushes' => $this->totalFlushes,
                'avg_flush_duration_ms' => round($avgFlushDuration, 2),
            ],
        ];
    }

    /**
     * Get time since last flush in milliseconds.
     */
    public function getTimeSinceLastFlush(): float
    {
        return (microtime(true) * 1000) - $this->lastFlushTime;
    }

    /**
     * Force set last flush time (for testing).
     */
    public function setLastFlushTime(float $timeMs): void
    {
        $this->lastFlushTime = $timeMs;
    }
}
