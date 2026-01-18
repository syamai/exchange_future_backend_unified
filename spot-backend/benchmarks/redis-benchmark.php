<?php
/**
 * Redis Operations Benchmark
 *
 * Compares the old approach (ZRANGE + ZREM) vs new approach (ZPOPMIN)
 * for order queue operations.
 */

declare(strict_types=1);

class RedisBenchmark
{
    private $redis;
    private string $testKeyOld = 'benchmark:old:queue';
    private string $testKeyNew = 'benchmark:new:queue';

    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }

    public function cleanup(): void
    {
        $this->redis->del($this->testKeyOld);
        $this->redis->del($this->testKeyNew);
    }

    /**
     * Old approach: ZRANGE + ZREM (two commands, not atomic)
     */
    public function benchmarkOldApproach(int $count): array
    {
        $this->redis->del($this->testKeyOld);

        // Populate queue
        $populateStart = microtime(true);
        for ($i = 1; $i <= $count; $i++) {
            $data = json_encode(['id' => $i, 'action' => 'add', 'price' => rand(40000, 60000)]);
            $this->redis->zAdd($this->testKeyOld, $i, $data);
        }
        $populateEnd = microtime(true);
        $populateTime = ($populateEnd - $populateStart) * 1000;

        // Process queue with old approach
        $processStart = microtime(true);
        $processed = 0;
        while (true) {
            $result = $this->redis->zRange($this->testKeyOld, 0, 0);
            if (empty($result)) {
                break;
            }
            $this->redis->zRem($this->testKeyOld, $result[0]);
            $processed++;
        }
        $processEnd = microtime(true);
        $processTime = ($processEnd - $processStart) * 1000;

        return [
            'approach' => 'ZRANGE+ZREM',
            'count' => $count,
            'populate_ms' => round($populateTime, 2),
            'process_ms' => round($processTime, 2),
            'processed' => $processed,
            'ops_per_sec' => $processTime > 0 ? round($processed / ($processTime / 1000)) : 0,
        ];
    }

    /**
     * New approach: ZPOPMIN (single atomic command)
     */
    public function benchmarkNewApproach(int $count): array
    {
        $this->redis->del($this->testKeyNew);

        // Populate queue
        $populateStart = microtime(true);
        for ($i = 1; $i <= $count; $i++) {
            $data = json_encode(['id' => $i, 'action' => 'add', 'price' => rand(40000, 60000)]);
            $this->redis->zAdd($this->testKeyNew, $i, $data);
        }
        $populateEnd = microtime(true);
        $populateTime = ($populateEnd - $populateStart) * 1000;

        // Process queue with new approach (ZPOPMIN)
        $processStart = microtime(true);
        $processed = 0;
        while (true) {
            $result = $this->redis->zPopMin($this->testKeyNew, 1);
            if (empty($result)) {
                break;
            }
            $processed++;
        }
        $processEnd = microtime(true);
        $processTime = ($processEnd - $processStart) * 1000;

        return [
            'approach' => 'ZPOPMIN',
            'count' => $count,
            'populate_ms' => round($populateTime, 2),
            'process_ms' => round($processTime, 2),
            'processed' => $processed,
            'ops_per_sec' => $processTime > 0 ? round($processed / ($processTime / 1000)) : 0,
        ];
    }

    /**
     * Batch ZPOPMIN benchmark
     */
    public function benchmarkBatchApproach(int $count, int $batchSize = 10): array
    {
        $this->redis->del($this->testKeyNew);

        // Populate queue
        $populateStart = microtime(true);
        for ($i = 1; $i <= $count; $i++) {
            $data = json_encode(['id' => $i, 'action' => 'add', 'price' => rand(40000, 60000)]);
            $this->redis->zAdd($this->testKeyNew, $i, $data);
        }
        $populateEnd = microtime(true);
        $populateTime = ($populateEnd - $populateStart) * 1000;

        // Process queue with batch ZPOPMIN
        $processStart = microtime(true);
        $processed = 0;
        while (true) {
            $result = $this->redis->zPopMin($this->testKeyNew, $batchSize);
            if (empty($result)) {
                break;
            }
            $processed += count($result);
        }
        $processEnd = microtime(true);
        $processTime = ($processEnd - $processStart) * 1000;

        return [
            'approach' => "ZPOPMIN(batch={$batchSize})",
            'count' => $count,
            'populate_ms' => round($populateTime, 2),
            'process_ms' => round($processTime, 2),
            'processed' => $processed,
            'ops_per_sec' => $processTime > 0 ? round($processed / ($processTime / 1000)) : 0,
        ];
    }
}

// Check if Redis extension is available
if (!extension_loaded('redis')) {
    die("Redis extension is not installed. Please install: pecl install redis\n");
}

// Run benchmarks
echo "=== Redis Queue Operations Benchmark ===\n\n";

$benchmark = new RedisBenchmark();

$testSizes = [1000, 5000, 10000, 50000];

echo str_pad("Approach", 25) . str_pad("Count", 10) . str_pad("Pop(ms)", 12);
echo str_pad("Processed", 12) . str_pad("Ops/sec", 12) . "\n";
echo str_repeat("-", 75) . "\n";

foreach ($testSizes as $size) {
    // Old approach
    $result = $benchmark->benchmarkOldApproach($size);
    echo str_pad($result['approach'], 25);
    echo str_pad((string)$result['count'], 10);
    echo str_pad((string)$result['process_ms'], 12);
    echo str_pad((string)$result['processed'], 12);
    echo str_pad((string)$result['ops_per_sec'], 12);
    echo "\n";

    // New approach (single)
    $result = $benchmark->benchmarkNewApproach($size);
    echo str_pad($result['approach'], 25);
    echo str_pad((string)$result['count'], 10);
    echo str_pad((string)$result['process_ms'], 12);
    echo str_pad((string)$result['processed'], 12);
    echo str_pad((string)$result['ops_per_sec'], 12);
    echo "\n";

    // New approach (batch=10)
    $result = $benchmark->benchmarkBatchApproach($size, 10);
    echo str_pad($result['approach'], 25);
    echo str_pad((string)$result['count'], 10);
    echo str_pad((string)$result['process_ms'], 12);
    echo str_pad((string)$result['processed'], 12);
    echo str_pad((string)$result['ops_per_sec'], 12);
    echo "\n";

    // New approach (batch=50)
    $result = $benchmark->benchmarkBatchApproach($size, 50);
    echo str_pad($result['approach'], 25);
    echo str_pad((string)$result['count'], 10);
    echo str_pad((string)$result['process_ms'], 12);
    echo str_pad((string)$result['processed'], 12);
    echo str_pad((string)$result['ops_per_sec'], 12);
    echo "\n\n";
}

$benchmark->cleanup();
echo "=== Benchmark Complete ===\n";
