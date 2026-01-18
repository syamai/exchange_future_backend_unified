<?php
/**
 * Fast InMemoryOrderBook Performance Benchmark
 *
 * Uses hash-based index for O(1) lookup instead of sorted array for each insert.
 * This matches the actual implementation in app/Services/InMemoryOrderBook.php
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
        $this->updated_at = (int)(microtime(true) * 1000000);
    }
}

// Optimized InMemoryOrderBook
class OptimizedOrderBook
{
    private array $buyOrders = [];      // price => [orderId => Order]
    private array $sellOrders = [];     // price => [orderId => Order]
    private array $orderIndex = [];     // orderId => Order

    private ?string $bestBuyPrice = null;
    private ?string $bestSellPrice = null;

    public function addOrder(Order $order): void
    {
        $this->orderIndex[$order->id] = $order;
        $price = $order->price;

        if ($order->trade_type === 'buy') {
            if (!isset($this->buyOrders[$price])) {
                $this->buyOrders[$price] = [];
            }
            $this->buyOrders[$price][$order->id] = $order;

            if ($this->bestBuyPrice === null || bccomp($price, $this->bestBuyPrice, 8) > 0) {
                $this->bestBuyPrice = $price;
            }
        } else {
            if (!isset($this->sellOrders[$price])) {
                $this->sellOrders[$price] = [];
            }
            $this->sellOrders[$price][$order->id] = $order;

            if ($this->bestSellPrice === null || bccomp($price, $this->bestSellPrice, 8) < 0) {
                $this->bestSellPrice = $price;
            }
        }
    }

    public function removeOrder(int $orderId): bool
    {
        if (!isset($this->orderIndex[$orderId])) {
            return false;
        }

        $order = $this->orderIndex[$orderId];
        unset($this->orderIndex[$orderId]);
        $price = $order->price;

        if ($order->trade_type === 'buy') {
            unset($this->buyOrders[$price][$orderId]);
            if (empty($this->buyOrders[$price])) {
                unset($this->buyOrders[$price]);
                $this->bestBuyPrice = $this->findBestBuyPrice();
            }
        } else {
            unset($this->sellOrders[$price][$orderId]);
            if (empty($this->sellOrders[$price])) {
                unset($this->sellOrders[$price]);
                $this->bestSellPrice = $this->findBestSellPrice();
            }
        }

        return true;
    }

    private function findBestBuyPrice(): ?string
    {
        if (empty($this->buyOrders)) {
            return null;
        }
        $prices = array_keys($this->buyOrders);
        usort($prices, fn($a, $b) => bccomp($b, $a, 8));
        return $prices[0];
    }

    private function findBestSellPrice(): ?string
    {
        if (empty($this->sellOrders)) {
            return null;
        }
        $prices = array_keys($this->sellOrders);
        usort($prices, fn($a, $b) => bccomp($a, $b, 8));
        return $prices[0];
    }

    public function getMatchablePair(): ?array
    {
        if ($this->bestBuyPrice === null || $this->bestSellPrice === null) {
            return null;
        }

        if (bccomp($this->bestBuyPrice, $this->bestSellPrice, 8) < 0) {
            return null;
        }

        // Get first order at best buy price (FIFO)
        $buyOrders = $this->buyOrders[$this->bestBuyPrice];
        $buyOrder = reset($buyOrders);

        // Get first order at best sell price (FIFO)
        $sellOrders = $this->sellOrders[$this->bestSellPrice];
        $sellOrder = reset($sellOrders);

        // Remove matched orders
        $this->removeOrder($buyOrder->id);
        $this->removeOrder($sellOrder->id);

        return ['buy' => $buyOrder, 'sell' => $sellOrder];
    }

    public function getStats(): array
    {
        $buyCount = 0;
        foreach ($this->buyOrders as $orders) {
            $buyCount += count($orders);
        }
        $sellCount = 0;
        foreach ($this->sellOrders as $orders) {
            $sellCount += count($orders);
        }

        return [
            'buy_orders' => $buyCount,
            'sell_orders' => $sellCount,
            'buy_price_levels' => count($this->buyOrders),
            'sell_price_levels' => count($this->sellOrders),
            'best_buy' => $this->bestBuyPrice,
            'best_sell' => $this->bestSellPrice,
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
        // Create overlapping price range for matches
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
    $orderBook = new OptimizedOrderBook();

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
        'add_per_sec' => $addTime > 0 ? round($orderCount / ($addTime / 1000)) : 0,
        'match_per_sec' => $matchTime > 0 ? round($matchCount / ($matchTime / 1000)) : 0,
        'remaining' => $orderBook->getStats(),
    ];
}

// Run benchmarks
echo "=== Optimized InMemoryOrderBook Performance Benchmark ===\n\n";

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
    echo str_pad((string)$result['add_per_sec'], 12);
    echo str_pad((string)$result['match_per_sec'], 12);
    echo "\n";
}

echo "\n";

// Additional: TPS estimation with continuous matching
echo "=== Continuous Matching TPS Estimation ===\n\n";

$orderBook = new OptimizedOrderBook();
$targetOrders = 10000;

// Pre-populate with orders
for ($i = 1; $i <= $targetOrders; $i++) {
    $isBuy = rand(0, 1) === 1;
    $basePrice = 50000.00;
    $priceOffset = rand(-100, 100); // Tighter spread for more matches
    $price = bcadd((string)$basePrice, (string)$priceOffset, 8);
    $quantity = bcdiv((string)rand(1, 100), '10', 8);
    $orderBook->addOrder(new Order($i, $isBuy ? 'buy' : 'sell', $price, $quantity));
}

// Measure matching TPS
$matchStart = microtime(true);
$matchCount = 0;
$maxMatches = 5000;

while ($matchCount < $maxMatches) {
    $pair = $orderBook->getMatchablePair();
    if (!$pair) {
        // Add more orders to continue matching
        for ($j = 0; $j < 10; $j++) {
            $isBuy = rand(0, 1) === 1;
            $priceOffset = rand(-100, 100);
            $price = bcadd('50000', (string)$priceOffset, 8);
            $orderBook->addOrder(new Order($targetOrders + $matchCount + $j, $isBuy ? 'buy' : 'sell', $price, '1'));
        }
        continue;
    }
    $matchCount++;
}

$matchEnd = microtime(true);
$elapsedSec = $matchEnd - $matchStart;
$tps = $matchCount / $elapsedSec;

echo "Matched {$matchCount} pairs in " . round($elapsedSec * 1000, 2) . " ms\n";
echo "Estimated Pure Matching TPS: " . number_format(round($tps)) . "\n";
echo "\nNote: This is pure in-memory matching without DB operations.\n";
echo "Actual TPS will be lower due to DB writes, Redis ops, network latency.\n";

echo "\n=== Benchmark Complete ===\n";
