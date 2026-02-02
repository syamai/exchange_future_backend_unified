<?php

namespace App\Services\Buffer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Synchronous write buffer for testing and debugging.
 *
 * Immediately writes each change to the database without buffering.
 * Use this when:
 * - Running unit/integration tests that need immediate DB state
 * - Debugging production issues
 * - When Swoole is not available
 */
class SyncWriteBuffer implements WriteBufferInterface
{
    private int $totalOrdersWritten = 0;
    private int $totalTradesWritten = 0;
    private int $totalBalancesWritten = 0;

    /**
     * Immediately write order update to database.
     */
    public function addOrder(int $orderId, array $data): void
    {
        $data['updated_at'] = $data['updated_at'] ?? now()->getTimestampMs();

        DB::connection('master')->table('orders')
            ->where('id', $orderId)
            ->update($data);

        $this->totalOrdersWritten++;
    }

    /**
     * Immediately insert trade to database.
     */
    public function addTrade(array $data): void
    {
        DB::connection('master')->table('order_transactions')->insert($data);
        $this->totalTradesWritten++;
    }

    /**
     * Immediately update balance in database.
     */
    public function addBalanceUpdate(int $userId, string $currency, array $changes): void
    {
        $tableName = "spot_{$currency}_accounts";
        $sets = [];
        $bindings = [];

        if (isset($changes['available_balance'])) {
            $sets[] = 'available_balance = available_balance + ?';
            $bindings[] = $changes['available_balance'];
        }

        if (isset($changes['total_balance'])) {
            $sets[] = 'total_balance = total_balance + ?';
            $bindings[] = $changes['total_balance'];
        }

        if (!empty($sets)) {
            $bindings[] = $userId;
            $sql = "UPDATE {$tableName} SET " . implode(', ', $sets) . " WHERE id = ?";
            DB::connection('master')->statement($sql, $bindings);
            $this->totalBalancesWritten++;
        }
    }

    /**
     * No-op for sync buffer (already written).
     */
    public function flush(): FlushResult
    {
        return FlushResult::empty();
    }

    /**
     * Always returns false (no buffering).
     */
    public function shouldFlush(): bool
    {
        return false;
    }

    /**
     * Get statistics.
     */
    public function getStats(): array
    {
        return [
            'buffer' => [
                'orders' => 0,
                'trades' => 0,
                'balances' => 0,
                'total' => 0,
            ],
            'config' => [
                'mode' => 'synchronous',
            ],
            'totals' => [
                'orders_written' => $this->totalOrdersWritten,
                'trades_written' => $this->totalTradesWritten,
                'balances_written' => $this->totalBalancesWritten,
                'flushes' => 0,
                'avg_flush_duration_ms' => 0,
            ],
        ];
    }

    /**
     * No-op for sync buffer (nothing buffered).
     */
    public function clear(): void
    {
        // Nothing to clear
    }
}
