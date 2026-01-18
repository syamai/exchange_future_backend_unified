<?php
/**
 * InMemoryOrderBook Performance Benchmark
 *
 * Tests the pure PHP algorithm for order matching without Laravel dependencies.
 * Simulates the Phase 3 InMemoryOrderBook implementation.
 */

declare(strict_types=1);

// Simulated Order class
class Order
{
    public int $id;
    public string $trade_type;
    public string $price;
    public string $quantity;
    public string $remaining;
    public int $updated_at;

    public function __construct(int $id, string $trade_type, string $price, string $quantity)
    {
        $this->id = $id;
        $this->trade_type = $trade_type;
        $this->price = $price;
        $this->quantity = $quantity;
        $this->remaining = $quantity;
        $this->updated_at = time() * 1000 + rand(0, 999);
    }
}

// Simplified InMemoryOrderBook (same logic as implemented)
class InMemoryOrderBook
{
    private array $buyOrders = [];
    private array $sellOrders = [];
    private array $orderIndex = [];

    public function addOrder(Order $order): void
    {
        $this->orderIndex[$order->id] = $order;

        if ($order->trade_type === 'buy') {
            $this->buyOrders[$order->id] = $order;
            uasort($this->buyOrders, function ($a, $b) {
                $priceComp = bccomp($b->price, $a->price, 8);
                if ($priceComp !== 0) {
                    return $priceComp;
                }
                return $a->updated_at <=> $b->updated_at;
            });
        } else {
            $this->sellOrders[$order->id] = $order;
            uasort($this->sellOrders, function ($a, $b) {
                $priceComp = bccomp($a->price, $b->price, 8);
                if ($priceComp !== 0) {
                    return $priceComp;
                }
                return $a->updated_at <=> $b->updated_at;
            });
        }
    }

    public function removeOrder(int $orderId): bool
    {
        if (!isset($this->orderIndex[$orderId])) {
            return false;
        }
        $order = $this->orderIndex[$orderId];
        unset($this->orderIndex[$orderId]);

        if ($order->trade_type === 'buy') {
            unset($this->buyOrders[$orderId]);
        } else {
            unset($this->sellOrders[$orderId]);
        }
        return true;
    }

    public function getMatchablePair(): ?array
    {
        if (empty($this->buyOrders) || empty($this->sellOrders)) {
            return null;
        }

        $topBuy = reset($this->buyOrders);
        $topSell = reset($this->sellOrders);

        if (!$topBuy || !$topSell) {
            return null;
        }

        if (bccomp($topBuy->price, $topSell->price, 8) >= 0) {
            $this->removeOrder($topBuy->id);
            $this->removeOrder($topSell->id);
            return ['buy' => $topBuy, 'sell' => $topSell];
        }

        return null;
    }

    public function getStats(): array
    {
        return [
            'buy_orders' => count($this->buyOrders),
            'sell_orders' => count($this->sellOrders),
            'total_indexed' => count($this->orderIndex),
        ];
    }
}

// Benchmark functions
function generateOrders(int $count): array
{
    $orders = [];
    $basePrice = 50000.00;

    for ($i = 1; $i <= $count; $i++) {
        $isBuy = rand(0, 1) === 1;
        $priceOffset = rand(-500, 500);
        $price = bcadd((string)$basePrice, (string)$priceOffset, 8);
        $quantity = bcdiv((string)rand(1, 100), '10', 8);

        $orders[] = new Order($i, $isBuy ? 'buy' : 'sell', $price, $quantity);
    }

    return $orders;
}

function benchmarkOrderBook(int $orderCount): array
{
    $orders = generateOrders($orderCount);
    $orderBook = new InMemoryOrderBook();

    // Benchmark: Add orders
    $addStart = microtime(true);
    foreach ($orders as $order) {
        $orderBook->addOrder($order);
    }
    $addEnd = microtime(true);
    $addTime = ($addEnd - $addStart) * 1000;

    // Benchmark: Match orders
    $matchStart = microtime(true);
    $matchCount = 0;
    while ($pair = $orderBook->getMatchablePair()) {
        $matchCount++;
    }
    $matchEnd = microtime(true);
    $matchTime = ($matchEnd - $matchStart) * 1000;

    $totalTime = $addTime + $matchTime;

    return [
        'order_count' => $orderCount,
        'add_time_ms' => round($addTime, 2),
        'match_time_ms' => round($matchTime, 2),
        'total_time_ms' => round($totalTime, 2),
        'matched_pairs' => $matchCount,
        'orders_per_sec' => round($orderCount / ($totalTime / 1000)),
        'matches_per_sec' => $matchTime > 0 ? round($matchCount / ($matchTime / 1000)) : 0,
        'remaining' => $orderBook->getStats(),
    ];
}

// Run benchmarks
echo "=== InMemoryOrderBook Performance Benchmark ===\n\n";

$testSizes = [1000, 5000, 10000, 50000, 100000];

echo str_pad("Orders", 10) . str_pad("Add(ms)", 12) . str_pad("Match(ms)", 12) . str_pad("Total(ms)", 12);
echo str_pad("Matched", 10) . str_pad("Add/sec", 12) . str_pad("Match/sec", 12) . "\n";
echo str_repeat("-", 80) . "\n";

foreach ($testSizes as $size) {
    $result = benchmarkOrderBook($size);
    echo str_pad((string)$result['order_count'], 10);
    echo str_pad((string)$result['add_time_ms'], 12);
    echo str_pad((string)$result['match_time_ms'], 12);
    echo str_pad((string)$result['total_time_ms'], 12);
    echo str_pad((string)$result['matched_pairs'], 10);
    echo str_pad((string)$result['orders_per_sec'], 12);
    echo str_pad((string)$result['matches_per_sec'], 12);
    echo "\n";
}

echo "\n=== Benchmark Complete ===\n";
