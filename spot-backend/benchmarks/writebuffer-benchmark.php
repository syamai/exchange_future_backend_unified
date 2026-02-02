<?php

/**
 * WriteBuffer Performance Benchmark
 *
 * Measures the performance improvement of batch DB writes vs sync writes.
 *
 * Usage:
 *   php benchmarks/writebuffer-benchmark.php
 *
 * Requirements:
 *   - MySQL connection (spot_backend database)
 *   - .env.testing or .env configured
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Services\Buffer\WriteBuffer;
use App\Services\Buffer\FlushResult;
use App\Utils\BigNumber;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Configuration
$BATCH_SIZES = [10, 50, 100, 200, 500];
$ITERATIONS = 3;

echo "=== WriteBuffer Performance Benchmark ===\n\n";
echo "Environment: " . app()->environment() . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "PHP: " . PHP_VERSION . "\n\n";

/**
 * Generate mock order data
 */
function generateOrderData(int $id): array
{
    return [
        'status' => 'executing',
        'executed_quantity' => (string)rand(1, 100),
        'executed_price' => (string)(rand(1000, 5000) / 100),
        'fee' => (string)(rand(1, 100) / 1000),
        'updated_at' => now()->timestamp * 1000,
    ];
}

/**
 * Generate mock trade data
 */
function generateTradeData(int $index): array
{
    return [
        'buyer_id' => rand(1, 1000),
        'seller_id' => rand(1, 1000),
        'buy_order_id' => rand(1, 10000),
        'sell_order_id' => rand(1, 10000),
        'currency' => 'usd',
        'coin' => 'btc',
        'quantity' => (string)rand(1, 100),
        'price' => (string)(rand(1000, 5000) / 100),
        'buy_fee' => (string)(rand(1, 100) / 1000),
        'sell_fee' => (string)(rand(1, 100) / 1000),
        'is_buyer_maker' => rand(0, 1),
        'created_at' => now()->timestamp * 1000,
        'updated_at' => now()->timestamp * 1000,
    ];
}

/**
 * Generate mock balance update data
 */
function generateBalanceData(int $userId, string $currency): array
{
    $amount = (string)(rand(-1000, 1000) / 100);
    return [
        'available_balance' => $amount,
        'total_balance' => $amount,
    ];
}

/**
 * Benchmark buffer operations (add to buffer only, no flush)
 */
function benchmarkBufferOperations(int $count): array
{
    $buffer = new WriteBuffer(10000, 60000, 3); // Large buffer, no auto-flush

    $startTime = microtime(true);

    for ($i = 0; $i < $count; $i++) {
        // Add order update
        $buffer->addOrder($i + 1, generateOrderData($i + 1));

        // Add trade
        $buffer->addTrade(generateTradeData($i));

        // Add balance updates (2 per match: buyer and seller)
        $buffer->addBalanceUpdate(rand(1, 1000), 'usd', generateBalanceData(1, 'usd'));
        $buffer->addBalanceUpdate(rand(1, 1000), 'btc', generateBalanceData(2, 'btc'));
    }

    $endTime = microtime(true);
    $durationMs = ($endTime - $startTime) * 1000;

    $stats = $buffer->getStats();

    return [
        'count' => $count,
        'duration_ms' => $durationMs,
        'ops_per_second' => $count / ($durationMs / 1000),
        'ms_per_op' => $durationMs / $count,
        'buffer_stats' => $stats,
    ];
}

/**
 * Benchmark sync writes (individual DB writes)
 */
function benchmarkSyncWrites(int $count): array
{
    $startTime = microtime(true);

    $ordersWritten = 0;
    $tradesWritten = 0;

    DB::connection('master')->beginTransaction();

    try {
        for ($i = 0; $i < $count; $i++) {
            // Simulate individual order update
            // In real scenario, this would be: Order::where('id', $id)->update($data);
            // We use raw insert for benchmark accuracy
            DB::connection('master')->table('benchmark_orders')->insert([
                'order_id' => $i + 1,
                'status' => 'executing',
                'executed_quantity' => (string)rand(1, 100),
                'executed_price' => (string)(rand(1000, 5000) / 100),
                'fee' => (string)(rand(1, 100) / 1000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ordersWritten++;

            // Simulate individual trade insert
            DB::connection('master')->table('benchmark_trades')->insert([
                'buyer_id' => rand(1, 1000),
                'seller_id' => rand(1, 1000),
                'buy_order_id' => rand(1, 10000),
                'sell_order_id' => rand(1, 10000),
                'currency' => 'usd',
                'coin' => 'btc',
                'quantity' => (string)rand(1, 100),
                'price' => (string)(rand(1000, 5000) / 100),
                'buy_fee' => (string)(rand(1, 100) / 1000),
                'sell_fee' => (string)(rand(1, 100) / 1000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $tradesWritten++;
        }

        DB::connection('master')->commit();
    } catch (Exception $e) {
        DB::connection('master')->rollBack();
        throw $e;
    }

    $endTime = microtime(true);
    $durationMs = ($endTime - $startTime) * 1000;

    return [
        'count' => $count,
        'duration_ms' => $durationMs,
        'ops_per_second' => $count / ($durationMs / 1000),
        'ms_per_op' => $durationMs / $count,
        'orders_written' => $ordersWritten,
        'trades_written' => $tradesWritten,
    ];
}

/**
 * Benchmark batch writes (simulated WriteBuffer flush)
 */
function benchmarkBatchWrites(int $count, int $batchSize): array
{
    $startTime = microtime(true);

    $ordersWritten = 0;
    $tradesWritten = 0;
    $flushCount = 0;

    $orderBatch = [];
    $tradeBatch = [];

    for ($i = 0; $i < $count; $i++) {
        $orderBatch[] = [
            'order_id' => $i + 1,
            'status' => 'executing',
            'executed_quantity' => (string)rand(1, 100),
            'executed_price' => (string)(rand(1000, 5000) / 100),
            'fee' => (string)(rand(1, 100) / 1000),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $tradeBatch[] = [
            'buyer_id' => rand(1, 1000),
            'seller_id' => rand(1, 1000),
            'buy_order_id' => rand(1, 10000),
            'sell_order_id' => rand(1, 10000),
            'currency' => 'usd',
            'coin' => 'btc',
            'quantity' => (string)rand(1, 100),
            'price' => (string)(rand(1000, 5000) / 100),
            'buy_fee' => (string)(rand(1, 100) / 1000),
            'sell_fee' => (string)(rand(1, 100) / 1000),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Flush when batch is full
        if (count($orderBatch) >= $batchSize) {
            DB::connection('master')->beginTransaction();
            try {
                DB::connection('master')->table('benchmark_orders')->insert($orderBatch);
                $ordersWritten += count($orderBatch);

                DB::connection('master')->table('benchmark_trades')->insert($tradeBatch);
                $tradesWritten += count($tradeBatch);

                DB::connection('master')->commit();
                $flushCount++;
            } catch (Exception $e) {
                DB::connection('master')->rollBack();
                throw $e;
            }

            $orderBatch = [];
            $tradeBatch = [];
        }
    }

    // Flush remaining
    if (!empty($orderBatch)) {
        DB::connection('master')->beginTransaction();
        try {
            DB::connection('master')->table('benchmark_orders')->insert($orderBatch);
            $ordersWritten += count($orderBatch);

            DB::connection('master')->table('benchmark_trades')->insert($tradeBatch);
            $tradesWritten += count($tradeBatch);

            DB::connection('master')->commit();
            $flushCount++;
        } catch (Exception $e) {
            DB::connection('master')->rollBack();
            throw $e;
        }
    }

    $endTime = microtime(true);
    $durationMs = ($endTime - $startTime) * 1000;

    return [
        'count' => $count,
        'batch_size' => $batchSize,
        'duration_ms' => $durationMs,
        'ops_per_second' => $count / ($durationMs / 1000),
        'ms_per_op' => $durationMs / $count,
        'orders_written' => $ordersWritten,
        'trades_written' => $tradesWritten,
        'flush_count' => $flushCount,
    ];
}

/**
 * Create benchmark tables
 */
function createBenchmarkTables(): void
{
    $schema = DB::connection('master')->getSchemaBuilder();

    // Drop existing tables
    $schema->dropIfExists('benchmark_orders');
    $schema->dropIfExists('benchmark_trades');

    // Create orders table
    $schema->create('benchmark_orders', function ($table) {
        $table->id();
        $table->integer('order_id');
        $table->string('status');
        $table->string('executed_quantity');
        $table->string('executed_price');
        $table->string('fee');
        $table->timestamps();
    });

    // Create trades table
    $schema->create('benchmark_trades', function ($table) {
        $table->id();
        $table->integer('buyer_id');
        $table->integer('seller_id');
        $table->integer('buy_order_id');
        $table->integer('sell_order_id');
        $table->string('currency');
        $table->string('coin');
        $table->string('quantity');
        $table->string('price');
        $table->string('buy_fee');
        $table->string('sell_fee');
        $table->timestamps();
    });

    echo "Benchmark tables created.\n\n";
}

/**
 * Clean up benchmark tables
 */
function cleanupBenchmarkTables(): void
{
    DB::connection('master')->table('benchmark_orders')->truncate();
    DB::connection('master')->table('benchmark_trades')->truncate();
}

// Main execution
try {
    // Create tables
    createBenchmarkTables();

    echo "=== 1. Buffer Operations (Memory Only) ===\n\n";
    echo "Measuring time to add operations to buffer (no DB writes)\n\n";

    $bufferResults = [];
    foreach ([100, 500, 1000, 5000, 10000] as $count) {
        $result = benchmarkBufferOperations($count);
        $bufferResults[] = $result;
        printf(
            "  %5d ops: %.2f ms (%.0f ops/sec, %.4f ms/op)\n",
            $result['count'],
            $result['duration_ms'],
            $result['ops_per_second'],
            $result['ms_per_op']
        );
    }

    echo "\n=== 2. Sync Writes vs Batch Writes ===\n\n";

    $testCount = 500; // Number of operations to test

    echo "Testing with $testCount operations...\n\n";

    // Sync writes
    cleanupBenchmarkTables();
    echo "  Sync writes (individual inserts)... ";
    $syncResult = benchmarkSyncWrites($testCount);
    printf(
        "%.2f ms (%.0f ops/sec, %.4f ms/op)\n",
        $syncResult['duration_ms'],
        $syncResult['ops_per_second'],
        $syncResult['ms_per_op']
    );

    // Batch writes with different batch sizes
    echo "\n  Batch writes:\n";
    $batchResults = [];
    foreach ($BATCH_SIZES as $batchSize) {
        cleanupBenchmarkTables();
        $result = benchmarkBatchWrites($testCount, $batchSize);
        $batchResults[$batchSize] = $result;

        $improvement = (($syncResult['duration_ms'] - $result['duration_ms']) / $syncResult['duration_ms']) * 100;

        printf(
            "    Batch %3d: %.2f ms (%.0f ops/sec, %.4f ms/op) - %.1f%% faster, %d flushes\n",
            $batchSize,
            $result['duration_ms'],
            $result['ops_per_second'],
            $result['ms_per_op'],
            $improvement,
            $result['flush_count']
        );
    }

    echo "\n=== 3. Performance Summary ===\n\n";

    // Find best batch size
    $bestBatch = null;
    $bestTime = PHP_INT_MAX;
    foreach ($batchResults as $size => $result) {
        if ($result['duration_ms'] < $bestTime) {
            $bestTime = $result['duration_ms'];
            $bestBatch = $size;
        }
    }

    $speedup = $syncResult['duration_ms'] / $batchResults[$bestBatch]['duration_ms'];
    $estimatedTPS = $batchResults[$bestBatch]['ops_per_second'];

    printf("Sync writes:      %.2f ms for %d ops (%.0f ops/sec)\n",
        $syncResult['duration_ms'], $testCount, $syncResult['ops_per_second']);
    printf("Best batch (%d):  %.2f ms for %d ops (%.0f ops/sec)\n",
        $bestBatch, $batchResults[$bestBatch]['duration_ms'], $testCount, $batchResults[$bestBatch]['ops_per_second']);
    printf("\n");
    printf("Speedup: %.1fx faster\n", $speedup);
    printf("Estimated TPS with batch writes: %.0f\n", $estimatedTPS);

    echo "\n=== 4. TPS Projection ===\n\n";

    // Calculate projected TPS considering:
    // - Pure matching overhead (~0.05ms per match based on previous benchmarks)
    // - Buffer add overhead (measured above)
    // - Batch flush overhead (measured above)

    $pureMatchingMs = 0.05; // ms per match (from RESULTS.md)
    $bufferAddMs = $bufferResults[2]['ms_per_op']; // 1000 ops benchmark
    $batchFlushMs = $batchResults[$bestBatch]['duration_ms'] / $testCount;

    $totalMsPerMatch = $pureMatchingMs + $bufferAddMs + $batchFlushMs;
    $projectedTPS = 1000 / $totalMsPerMatch;

    printf("Per-match overhead breakdown:\n");
    printf("  - Pure matching:  %.4f ms\n", $pureMatchingMs);
    printf("  - Buffer add:     %.4f ms\n", $bufferAddMs);
    printf("  - Batch flush:    %.4f ms (amortized)\n", $batchFlushMs);
    printf("  - Total:          %.4f ms\n", $totalMsPerMatch);
    printf("\n");
    printf("Projected TPS: %.0f matches/sec\n", $projectedTPS);

    echo "\n=== Benchmark Complete ===\n";

} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
