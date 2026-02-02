<?php
/**
 * HeapOrderBook vs InMemoryOrderBook Performance Benchmark
 *
 * Tests O(log n) Heap vs O(n) Array insertion performance
 */

// Autoload
require_once __DIR__ . '/../app/Services/OrderBook/BuyOrderHeap.php';
require_once __DIR__ . '/../app/Services/OrderBook/SellOrderHeap.php';

use App\Services\OrderBook\BuyOrderHeap;
use App\Services\OrderBook\SellOrderHeap;

echo "=== HeapOrderBook Performance Benchmark ===\n\n";

// Test parameters
$testSizes = [100, 500, 1000, 2000, 5000, 10000];

// Results storage
$results = [];

foreach ($testSizes as $size) {
    echo "Testing with {$size} orders...\n";

    // Generate random orders
    $orders = [];
    for ($i = 0; $i < $size; $i++) {
        $orders[] = [
            'id' => $i + 1,
            'price' => number_format(rand(9000, 11000) + (rand(0, 99999) / 100000), 8, '.', ''),
            'quantity' => number_format(rand(1, 100) / 10, 8, '.', ''),
            'timestamp' => time() + $i,
        ];
    }

    // === Test 1: Heap Insert Performance ===
    $heap = new BuyOrderHeap();
    $heapStart = microtime(true);

    foreach ($orders as $order) {
        $heap->insert($order);
    }

    $heapInsertTime = (microtime(true) - $heapStart) * 1000;

    // === Test 2: Array Insert Performance (simulating O(n) insertion) ===
    $array = [];
    $arrayStart = microtime(true);

    foreach ($orders as $order) {
        // Find insertion position (O(n) search)
        $inserted = false;
        for ($i = 0; $i < count($array); $i++) {
            if (bccomp($order['price'], $array[$i]['price'], 8) > 0) {
                array_splice($array, $i, 0, [$order]);
                $inserted = true;
                break;
            }
        }
        if (!$inserted) {
            $array[] = $order;
        }
    }

    $arrayInsertTime = (microtime(true) - $arrayStart) * 1000;

    // === Test 3: Heap Extract Performance ===
    $heap2 = new BuyOrderHeap();
    foreach ($orders as $order) {
        $heap2->insert($order);
    }

    $heapExtractStart = microtime(true);
    $extractCount = min(100, $size);
    for ($i = 0; $i < $extractCount; $i++) {
        if (!$heap2->isEmpty()) {
            $heap2->extract();
        }
    }
    $heapExtractTime = (microtime(true) - $heapExtractStart) * 1000;

    // === Test 4: Array Shift Performance ===
    $arrayExtractStart = microtime(true);
    for ($i = 0; $i < $extractCount; $i++) {
        if (!empty($array)) {
            array_shift($array);
        }
    }
    $arrayExtractTime = (microtime(true) - $arrayExtractStart) * 1000;

    // Store results
    $results[$size] = [
        'heap_insert_ms' => round($heapInsertTime, 2),
        'array_insert_ms' => round($arrayInsertTime, 2),
        'insert_speedup' => round($arrayInsertTime / max($heapInsertTime, 0.01), 1),
        'heap_extract_ms' => round($heapExtractTime, 2),
        'array_extract_ms' => round($arrayExtractTime, 2),
    ];

    echo "  Heap Insert:  {$results[$size]['heap_insert_ms']} ms\n";
    echo "  Array Insert: {$results[$size]['array_insert_ms']} ms\n";
    echo "  Speedup:      {$results[$size]['insert_speedup']}x faster\n\n";
}

// Summary
echo "=== SUMMARY ===\n\n";
echo str_pad("Orders", 10) . str_pad("Heap (ms)", 12) . str_pad("Array (ms)", 12) . str_pad("Speedup", 10) . "\n";
echo str_repeat("-", 44) . "\n";

foreach ($results as $size => $data) {
    echo str_pad($size, 10);
    echo str_pad($data['heap_insert_ms'], 12);
    echo str_pad($data['array_insert_ms'], 12);
    echo str_pad($data['insert_speedup'] . "x", 10);
    echo "\n";
}

echo "\n=== ANALYSIS ===\n";
echo "As order count increases, the performance gap widens significantly.\n";
echo "Heap: O(n log n) total for n insertions\n";
echo "Array: O(n²) total for n insertions (each insertion is O(n))\n\n";

// Calculate theoretical improvement for 5000 TPS
$targetTps = 5000;
$heapTimeFor5k = $results[5000]['heap_insert_ms'] ?? 0;
$arrayTimeFor5k = $results[5000]['array_insert_ms'] ?? 0;

if ($heapTimeFor5k > 0) {
    $heapTps = round(5000 / ($heapTimeFor5k / 1000));
    $arrayTps = round(5000 / ($arrayTimeFor5k / 1000));

    echo "=== PROJECTED TPS ===\n";
    echo "Heap-based:  ~{$heapTps} inserts/sec\n";
    echo "Array-based: ~{$arrayTps} inserts/sec\n";
    echo "Target:      {$targetTps} TPS\n";
    echo "\nHeap implementation " . ($heapTps >= $targetTps ? "MEETS" : "does not meet") . " the target.\n";
}

echo "\n=== BENCHMARK COMPLETE ===\n";
