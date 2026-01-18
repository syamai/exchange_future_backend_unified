<?php
/**
 * Dynamic Polling Simulation Benchmark
 *
 * Compares fixed 200ms polling vs dynamic 1-50ms polling
 * to demonstrate latency improvement.
 */

declare(strict_types=1);

class PollingSimulator
{
    // Fixed polling constants (old approach)
    private const FIXED_SLEEP_US = 200000; // 200ms

    // Dynamic polling constants (new approach)
    private const MIN_SLEEP_US = 1000;     // 1ms minimum
    private const MAX_SLEEP_US = 50000;    // 50ms maximum
    private const EMPTY_THRESHOLD = 5;

    /**
     * Simulates fixed 200ms polling
     */
    public function simulateFixedPolling(array $orderArrivalTimes, int $simulationMs): array
    {
        $processedOrders = [];
        $currentTime = 0;
        $queuedOrders = [];
        $orderIndex = 0;
        $totalOrders = count($orderArrivalTimes);

        while ($currentTime < $simulationMs && count($processedOrders) < $totalOrders) {
            // Add orders that arrived before current time
            while ($orderIndex < $totalOrders && $orderArrivalTimes[$orderIndex] <= $currentTime) {
                $queuedOrders[] = [
                    'id' => $orderIndex + 1,
                    'arrival' => $orderArrivalTimes[$orderIndex],
                ];
                $orderIndex++;
            }

            // Process all queued orders
            while (!empty($queuedOrders)) {
                $order = array_shift($queuedOrders);
                $order['processed'] = $currentTime;
                $order['latency'] = $currentTime - $order['arrival'];
                $processedOrders[] = $order;
            }

            // Fixed 200ms sleep
            $currentTime += self::FIXED_SLEEP_US / 1000;
        }

        return $this->calculateStats($processedOrders, 'Fixed 200ms');
    }

    /**
     * Simulates dynamic 1-50ms polling
     */
    public function simulateDynamicPolling(array $orderArrivalTimes, int $simulationMs): array
    {
        $processedOrders = [];
        $currentTime = 0;
        $queuedOrders = [];
        $orderIndex = 0;
        $totalOrders = count($orderArrivalTimes);

        $sleepTimeUs = self::MIN_SLEEP_US;
        $consecutiveEmpty = 0;

        while ($currentTime < $simulationMs && count($processedOrders) < $totalOrders) {
            // Add orders that arrived before current time
            while ($orderIndex < $totalOrders && $orderArrivalTimes[$orderIndex] <= $currentTime) {
                $queuedOrders[] = [
                    'id' => $orderIndex + 1,
                    'arrival' => $orderArrivalTimes[$orderIndex],
                ];
                $orderIndex++;
            }

            // Process all queued orders
            $processed = false;
            while (!empty($queuedOrders)) {
                $order = array_shift($queuedOrders);
                $order['processed'] = $currentTime;
                $order['latency'] = $currentTime - $order['arrival'];
                $processedOrders[] = $order;
                $processed = true;
            }

            // Dynamic sleep adjustment
            if ($processed) {
                $sleepTimeUs = self::MIN_SLEEP_US;
                $consecutiveEmpty = 0;
            } else {
                $consecutiveEmpty++;
                if ($consecutiveEmpty > self::EMPTY_THRESHOLD) {
                    $sleepTimeUs = min($sleepTimeUs * 2, self::MAX_SLEEP_US);
                }
            }

            $currentTime += $sleepTimeUs / 1000;
        }

        return $this->calculateStats($processedOrders, 'Dynamic 1-50ms');
    }

    private function calculateStats(array $processedOrders, string $approach): array
    {
        if (empty($processedOrders)) {
            return [
                'approach' => $approach,
                'processed' => 0,
                'avg_latency_ms' => 0,
                'max_latency_ms' => 0,
                'min_latency_ms' => 0,
                'p50_latency_ms' => 0,
                'p95_latency_ms' => 0,
                'p99_latency_ms' => 0,
            ];
        }

        $latencies = array_column($processedOrders, 'latency');
        sort($latencies);

        $count = count($latencies);
        $p50Index = (int)($count * 0.50);
        $p95Index = (int)($count * 0.95);
        $p99Index = (int)($count * 0.99);

        return [
            'approach' => $approach,
            'processed' => $count,
            'avg_latency_ms' => round(array_sum($latencies) / $count, 2),
            'max_latency_ms' => round(max($latencies), 2),
            'min_latency_ms' => round(min($latencies), 2),
            'p50_latency_ms' => round($latencies[$p50Index], 2),
            'p95_latency_ms' => round($latencies[$p95Index], 2),
            'p99_latency_ms' => round($latencies[$p99Index], 2),
        ];
    }
}

/**
 * Generate random order arrival times
 */
function generateOrderArrivals(int $count, int $simulationMs): array
{
    $arrivals = [];
    for ($i = 0; $i < $count; $i++) {
        $arrivals[] = rand(0, $simulationMs - 100);
    }
    sort($arrivals);
    return $arrivals;
}

/**
 * Generate burst order arrivals (simulates high traffic)
 */
function generateBurstArrivals(int $count, int $simulationMs): array
{
    $arrivals = [];
    $burstCount = 10;
    $ordersPerBurst = $count / $burstCount;

    for ($burst = 0; $burst < $burstCount; $burst++) {
        $burstStart = ($simulationMs / $burstCount) * $burst;
        for ($i = 0; $i < $ordersPerBurst; $i++) {
            $arrivals[] = $burstStart + rand(0, 50); // 50ms burst window
        }
    }
    sort($arrivals);
    return $arrivals;
}

// Run simulation
echo "=== Polling Strategy Latency Simulation ===\n\n";

$simulator = new PollingSimulator();
$simulationMs = 10000; // 10 seconds

echo "Simulation: 10 seconds, measuring order processing latency\n\n";

// Test 1: Uniform distribution
echo "--- Test 1: Uniform Order Distribution ---\n";
$orderCounts = [100, 500, 1000, 2000];

echo str_pad("Orders", 10) . str_pad("Approach", 18) . str_pad("Avg(ms)", 10);
echo str_pad("P50(ms)", 10) . str_pad("P95(ms)", 10) . str_pad("P99(ms)", 10) . str_pad("Max(ms)", 10) . "\n";
echo str_repeat("-", 78) . "\n";

foreach ($orderCounts as $count) {
    $arrivals = generateOrderArrivals($count, $simulationMs);

    $fixedResult = $simulator->simulateFixedPolling($arrivals, $simulationMs);
    echo str_pad((string)$count, 10);
    echo str_pad($fixedResult['approach'], 18);
    echo str_pad((string)$fixedResult['avg_latency_ms'], 10);
    echo str_pad((string)$fixedResult['p50_latency_ms'], 10);
    echo str_pad((string)$fixedResult['p95_latency_ms'], 10);
    echo str_pad((string)$fixedResult['p99_latency_ms'], 10);
    echo str_pad((string)$fixedResult['max_latency_ms'], 10);
    echo "\n";

    $dynamicResult = $simulator->simulateDynamicPolling($arrivals, $simulationMs);
    echo str_pad("", 10);
    echo str_pad($dynamicResult['approach'], 18);
    echo str_pad((string)$dynamicResult['avg_latency_ms'], 10);
    echo str_pad((string)$dynamicResult['p50_latency_ms'], 10);
    echo str_pad((string)$dynamicResult['p95_latency_ms'], 10);
    echo str_pad((string)$dynamicResult['p99_latency_ms'], 10);
    echo str_pad((string)$dynamicResult['max_latency_ms'], 10);
    echo "\n";

    $improvement = $fixedResult['avg_latency_ms'] > 0
        ? round((1 - $dynamicResult['avg_latency_ms'] / $fixedResult['avg_latency_ms']) * 100, 1)
        : 0;
    echo str_pad("", 10) . "Improvement: {$improvement}%\n\n";
}

// Test 2: Burst traffic
echo "\n--- Test 2: Burst Traffic Pattern ---\n";

echo str_pad("Orders", 10) . str_pad("Approach", 18) . str_pad("Avg(ms)", 10);
echo str_pad("P50(ms)", 10) . str_pad("P95(ms)", 10) . str_pad("P99(ms)", 10) . str_pad("Max(ms)", 10) . "\n";
echo str_repeat("-", 78) . "\n";

foreach ($orderCounts as $count) {
    $arrivals = generateBurstArrivals($count, $simulationMs);

    $fixedResult = $simulator->simulateFixedPolling($arrivals, $simulationMs);
    echo str_pad((string)$count, 10);
    echo str_pad($fixedResult['approach'], 18);
    echo str_pad((string)$fixedResult['avg_latency_ms'], 10);
    echo str_pad((string)$fixedResult['p50_latency_ms'], 10);
    echo str_pad((string)$fixedResult['p95_latency_ms'], 10);
    echo str_pad((string)$fixedResult['p99_latency_ms'], 10);
    echo str_pad((string)$fixedResult['max_latency_ms'], 10);
    echo "\n";

    $dynamicResult = $simulator->simulateDynamicPolling($arrivals, $simulationMs);
    echo str_pad("", 10);
    echo str_pad($dynamicResult['approach'], 18);
    echo str_pad((string)$dynamicResult['avg_latency_ms'], 10);
    echo str_pad((string)$dynamicResult['p50_latency_ms'], 10);
    echo str_pad((string)$dynamicResult['p95_latency_ms'], 10);
    echo str_pad((string)$dynamicResult['p99_latency_ms'], 10);
    echo str_pad((string)$dynamicResult['max_latency_ms'], 10);
    echo "\n";

    $improvement = $fixedResult['avg_latency_ms'] > 0
        ? round((1 - $dynamicResult['avg_latency_ms'] / $fixedResult['avg_latency_ms']) * 100, 1)
        : 0;
    echo str_pad("", 10) . "Improvement: {$improvement}%\n\n";
}

echo "=== Simulation Complete ===\n";
