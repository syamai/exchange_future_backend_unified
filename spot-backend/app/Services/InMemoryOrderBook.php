<?php

namespace App\Services;

use App\Consts;
use App\Models\Order;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\Log;

/**
 * In-Memory Order Book for high-performance order matching.
 * Uses sorted arrays (by price) for O(1) best bid/ask access and O(log n) insertion.
 */
class InMemoryOrderBook
{
    // Price-time priority sorted arrays
    private array $buyOrders = [];   // Sorted by price DESC, then time ASC
    private array $sellOrders = [];  // Sorted by price ASC, then time ASC

    // Order index for O(1) lookup and removal
    private array $orderIndex = [];

    private string $currency;
    private string $coin;

    // Statistics
    private int $totalOrders = 0;
    private int $matchedPairs = 0;

    public function __construct(string $currency, string $coin)
    {
        $this->currency = $currency;
        $this->coin = $coin;
    }

    /**
     * Add an order to the orderbook.
     *
     * @param Order $order
     * @return void
     */
    public function addOrder(Order $order): void
    {
        if (!$order->canMatching()) {
            return;
        }

        // Skip if already exists
        if (isset($this->orderIndex[$order->id])) {
            return;
        }

        $orderData = [
            'id' => $order->id,
            'price' => $order->price,
            'quantity' => $order->quantity,
            'filled_quantity' => $order->filled_quantity,
            'trade_type' => $order->trade_type,
            'type' => $order->type,
            'user_id' => $order->user_id,
            'updated_at' => $order->updated_at,
            'order' => $order,
        ];

        $this->orderIndex[$order->id] = $orderData;

        if ($order->trade_type === Consts::ORDER_TRADE_TYPE_BUY) {
            $this->insertBuyOrder($orderData);
        } else {
            $this->insertSellOrder($orderData);
        }

        $this->totalOrders++;
    }

    /**
     * Remove an order from the orderbook.
     *
     * @param int $orderId
     * @return bool
     */
    public function removeOrder(int $orderId): bool
    {
        if (!isset($this->orderIndex[$orderId])) {
            return false;
        }

        $orderData = $this->orderIndex[$orderId];
        unset($this->orderIndex[$orderId]);

        if ($orderData['trade_type'] === Consts::ORDER_TRADE_TYPE_BUY) {
            $this->removeFromBuyOrders($orderId);
        } else {
            $this->removeFromSellOrders($orderId);
        }

        return true;
    }

    /**
     * Get the next matchable pair of orders.
     * Returns null if no match is possible.
     *
     * @return array|null ['buy' => Order, 'sell' => Order] or null
     */
    public function getMatchablePair(): ?array
    {
        // Clean up invalid orders first
        $this->cleanupInvalidOrders();

        if (empty($this->buyOrders) || empty($this->sellOrders)) {
            return null;
        }

        $topBuy = $this->buyOrders[0] ?? null;
        $topSell = $this->sellOrders[0] ?? null;

        if (!$topBuy || !$topSell) {
            return null;
        }

        // Check if orders can match (buy price >= sell price)
        $canMatch = $this->canOrdersMatch($topBuy, $topSell);

        if (!$canMatch) {
            return null;
        }

        // Remove from orderbook (will be re-added if partially filled)
        array_shift($this->buyOrders);
        array_shift($this->sellOrders);
        unset($this->orderIndex[$topBuy['id']]);
        unset($this->orderIndex[$topSell['id']]);

        $this->matchedPairs++;

        return [
            'buy' => $topBuy['order'],
            'sell' => $topSell['order'],
        ];
    }

    /**
     * Check if buy and sell orders can match.
     *
     * @param array $buyOrder
     * @param array $sellOrder
     * @return bool
     */
    private function canOrdersMatch(array $buyOrder, array $sellOrder): bool
    {
        // Market orders always match
        if ($this->isMarketType($buyOrder['type']) || $this->isMarketType($sellOrder['type'])) {
            return true;
        }

        // Limit orders: buy price >= sell price
        return bccomp($buyOrder['price'], $sellOrder['price'], 8) >= 0;
    }

    /**
     * Insert buy order maintaining price-time priority (highest price first).
     *
     * @param array $orderData
     * @return void
     */
    private function insertBuyOrder(array $orderData): void
    {
        $inserted = false;

        for ($i = 0; $i < count($this->buyOrders); $i++) {
            $cmp = bccomp($orderData['price'], $this->buyOrders[$i]['price'], 8);

            // Higher price comes first
            if ($cmp > 0) {
                array_splice($this->buyOrders, $i, 0, [$orderData]);
                $inserted = true;
                break;
            }

            // Same price: earlier time comes first
            if ($cmp === 0 && $orderData['updated_at'] < $this->buyOrders[$i]['updated_at']) {
                array_splice($this->buyOrders, $i, 0, [$orderData]);
                $inserted = true;
                break;
            }
        }

        if (!$inserted) {
            $this->buyOrders[] = $orderData;
        }
    }

    /**
     * Insert sell order maintaining price-time priority (lowest price first).
     *
     * @param array $orderData
     * @return void
     */
    private function insertSellOrder(array $orderData): void
    {
        $inserted = false;

        for ($i = 0; $i < count($this->sellOrders); $i++) {
            $cmp = bccomp($orderData['price'], $this->sellOrders[$i]['price'], 8);

            // Lower price comes first
            if ($cmp < 0) {
                array_splice($this->sellOrders, $i, 0, [$orderData]);
                $inserted = true;
                break;
            }

            // Same price: earlier time comes first
            if ($cmp === 0 && $orderData['updated_at'] < $this->sellOrders[$i]['updated_at']) {
                array_splice($this->sellOrders, $i, 0, [$orderData]);
                $inserted = true;
                break;
            }
        }

        if (!$inserted) {
            $this->sellOrders[] = $orderData;
        }
    }

    /**
     * Remove order from buy orders array.
     *
     * @param int $orderId
     * @return void
     */
    private function removeFromBuyOrders(int $orderId): void
    {
        foreach ($this->buyOrders as $i => $order) {
            if ($order['id'] === $orderId) {
                array_splice($this->buyOrders, $i, 1);
                return;
            }
        }
    }

    /**
     * Remove order from sell orders array.
     *
     * @param int $orderId
     * @return void
     */
    private function removeFromSellOrders(int $orderId): void
    {
        foreach ($this->sellOrders as $i => $order) {
            if ($order['id'] === $orderId) {
                array_splice($this->sellOrders, $i, 1);
                return;
            }
        }
    }

    /**
     * Clean up orders that are no longer valid (canceled, filled, etc.)
     *
     * @return void
     */
    private function cleanupInvalidOrders(): void
    {
        // Clean buy orders
        while (!empty($this->buyOrders)) {
            $top = $this->buyOrders[0];
            if (!isset($this->orderIndex[$top['id']])) {
                array_shift($this->buyOrders);
                continue;
            }

            // Refresh order status from DB occasionally
            $order = $top['order']->fresh();
            if (!$order || !$order->canMatching()) {
                array_shift($this->buyOrders);
                unset($this->orderIndex[$top['id']]);
                continue;
            }

            // Update the order reference
            $this->buyOrders[0]['order'] = $order;
            break;
        }

        // Clean sell orders
        while (!empty($this->sellOrders)) {
            $top = $this->sellOrders[0];
            if (!isset($this->orderIndex[$top['id']])) {
                array_shift($this->sellOrders);
                continue;
            }

            $order = $top['order']->fresh();
            if (!$order || !$order->canMatching()) {
                array_shift($this->sellOrders);
                unset($this->orderIndex[$top['id']]);
                continue;
            }

            $this->sellOrders[0]['order'] = $order;
            break;
        }
    }

    /**
     * Load all matchable orders from database.
     *
     * @return int Number of orders loaded
     */
    public function loadFromDatabase(): int
    {
        $orders = Order::where('currency', $this->currency)
            ->where('coin', $this->coin)
            ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
            ->orderBy('updated_at', 'asc')
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $this->addOrder($order);
            $count++;
        }

        Log::info("InMemoryOrderBook: Loaded {$count} orders for {$this->coin}/{$this->currency}");

        return $count;
    }

    /**
     * Check if order type is market.
     *
     * @param string $type
     * @return bool
     */
    private function isMarketType(string $type): bool
    {
        return $type === Consts::ORDER_TYPE_MARKET || $type === Consts::ORDER_TYPE_STOP_MARKET;
    }

    /**
     * Get order by ID.
     *
     * @param int $orderId
     * @return Order|null
     */
    public function getOrder(int $orderId): ?Order
    {
        return $this->orderIndex[$orderId]['order'] ?? null;
    }

    /**
     * Check if order exists in orderbook.
     *
     * @param int $orderId
     * @return bool
     */
    public function hasOrder(int $orderId): bool
    {
        return isset($this->orderIndex[$orderId]);
    }

    /**
     * Get orderbook statistics.
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'currency' => $this->currency,
            'coin' => $this->coin,
            'buy_orders' => count($this->buyOrders),
            'sell_orders' => count($this->sellOrders),
            'total_indexed' => count($this->orderIndex),
            'total_processed' => $this->totalOrders,
            'matched_pairs' => $this->matchedPairs,
            'best_bid' => $this->buyOrders[0]['price'] ?? null,
            'best_ask' => $this->sellOrders[0]['price'] ?? null,
        ];
    }

    /**
     * Get the spread (difference between best ask and best bid).
     *
     * @return string|null
     */
    public function getSpread(): ?string
    {
        if (empty($this->buyOrders) || empty($this->sellOrders)) {
            return null;
        }

        $bestBid = $this->buyOrders[0]['price'];
        $bestAsk = $this->sellOrders[0]['price'];

        return BigNumber::new($bestAsk)->sub($bestBid)->toString();
    }

    /**
     * Clear the orderbook.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->buyOrders = [];
        $this->sellOrders = [];
        $this->orderIndex = [];
        $this->totalOrders = 0;
        $this->matchedPairs = 0;
    }
}
