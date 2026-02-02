<?php

namespace App\Services\OrderBook;

use App\Consts;
use App\Models\Order;
use App\Services\Cache\OrderCacheService;
use Illuminate\Support\Facades\Log;

/**
 * High-performance OrderBook using Heap data structure (FR-DS-001)
 *
 * Performance improvements over array-based implementation:
 * - Insert: O(log n) vs O(n)
 * - Extract best: O(log n) vs O(1) - slight trade-off but amortized
 * - Remove by ID: O(n) - same, but uses lazy deletion for optimization
 */
class HeapOrderBook
{
    private BuyOrderHeap $buyHeap;
    private SellOrderHeap $sellHeap;

    // Order index for O(1) lookup and lazy deletion
    private array $orderIndex = [];

    // Deleted order IDs (for lazy deletion)
    private array $deletedIds = [];

    private string $currency;
    private string $coin;

    // Statistics
    private int $totalOrders = 0;
    private int $matchedPairs = 0;

    // Optional cache service
    private ?OrderCacheService $cacheService = null;

    public function __construct(string $currency, string $coin, ?OrderCacheService $cacheService = null)
    {
        $this->currency = strtolower(trim($currency));
        $this->coin = strtolower(trim($coin));
        $this->cacheService = $cacheService;

        $this->buyHeap = new BuyOrderHeap();
        $this->sellHeap = new SellOrderHeap();
    }

    /**
     * Add an order to the orderbook.
     * Time complexity: O(log n)
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

        $orderData = $this->createOrderData($order);
        $this->orderIndex[$order->id] = $orderData;

        if ($order->trade_type === Consts::ORDER_TRADE_TYPE_BUY) {
            $this->buyHeap->insert($orderData);
        } else {
            $this->sellHeap->insert($orderData);
        }

        // Update cache if available
        if ($this->cacheService) {
            $this->cacheService->set($order->id, $orderData);
        }

        $this->totalOrders++;
    }

    /**
     * Remove an order from the orderbook using lazy deletion.
     * Time complexity: O(1) for marking, actual removal is O(log n) during extraction
     */
    public function removeOrder(int $orderId): bool
    {
        if (!isset($this->orderIndex[$orderId])) {
            return false;
        }

        // Mark as deleted (lazy deletion)
        $this->deletedIds[$orderId] = true;
        unset($this->orderIndex[$orderId]);

        // Invalidate cache
        if ($this->cacheService) {
            $this->cacheService->invalidate($orderId);
        }

        return true;
    }

    /**
     * Get the next matchable pair of orders.
     * Time complexity: O(log n) amortized
     *
     * @return array|null ['buy' => Order, 'sell' => Order] or null
     */
    public function getMatchablePair(): ?array
    {
        $topBuy = $this->peekValidBuy();
        $topSell = $this->peekValidSell();

        if (!$topBuy || !$topSell) {
            return null;
        }

        // Check if orders can match (buy price >= sell price)
        if (!$this->canOrdersMatch($topBuy, $topSell)) {
            return null;
        }

        // Extract from heaps
        $this->extractValidBuy();
        $this->extractValidSell();

        unset($this->orderIndex[$topBuy['id']]);
        unset($this->orderIndex[$topSell['id']]);

        $this->matchedPairs++;

        return [
            'buy' => $topBuy['order'],
            'sell' => $topSell['order'],
        ];
    }

    /**
     * Peek the best valid buy order (skip deleted/invalid orders).
     */
    private function peekValidBuy(): ?array
    {
        while (!$this->buyHeap->isEmpty()) {
            $top = $this->buyHeap->top();

            // Check lazy deletion
            if (isset($this->deletedIds[$top['id']])) {
                $this->buyHeap->extract();
                unset($this->deletedIds[$top['id']]);
                continue;
            }

            // Check if still in index
            if (!isset($this->orderIndex[$top['id']])) {
                $this->buyHeap->extract();
                continue;
            }

            // Validate order status (refresh from cache or DB)
            $order = $this->refreshOrder($top);
            if (!$order || !$order->canMatching()) {
                $this->buyHeap->extract();
                unset($this->orderIndex[$top['id']]);
                continue;
            }

            // Update order reference
            $this->orderIndex[$top['id']]['order'] = $order;
            return $this->orderIndex[$top['id']];
        }

        return null;
    }

    /**
     * Peek the best valid sell order (skip deleted/invalid orders).
     */
    private function peekValidSell(): ?array
    {
        while (!$this->sellHeap->isEmpty()) {
            $top = $this->sellHeap->top();

            if (isset($this->deletedIds[$top['id']])) {
                $this->sellHeap->extract();
                unset($this->deletedIds[$top['id']]);
                continue;
            }

            if (!isset($this->orderIndex[$top['id']])) {
                $this->sellHeap->extract();
                continue;
            }

            $order = $this->refreshOrder($top);
            if (!$order || !$order->canMatching()) {
                $this->sellHeap->extract();
                unset($this->orderIndex[$top['id']]);
                continue;
            }

            $this->orderIndex[$top['id']]['order'] = $order;
            return $this->orderIndex[$top['id']];
        }

        return null;
    }

    /**
     * Extract the best valid buy order.
     */
    private function extractValidBuy(): ?array
    {
        if ($this->buyHeap->isEmpty()) {
            return null;
        }
        return $this->buyHeap->extract();
    }

    /**
     * Extract the best valid sell order.
     */
    private function extractValidSell(): ?array
    {
        if ($this->sellHeap->isEmpty()) {
            return null;
        }
        return $this->sellHeap->extract();
    }

    /**
     * Refresh order from cache or database.
     */
    private function refreshOrder(array $orderData): ?Order
    {
        // Try cache first (FR-PF-004)
        if ($this->cacheService) {
            $cached = $this->cacheService->getOrder($orderData['id']);
            if ($cached) {
                return $cached;
            }
        }

        // Fallback to database
        return $orderData['order']->fresh();
    }

    /**
     * Check if buy and sell orders can match.
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
     * Create order data array for heap insertion.
     */
    private function createOrderData(Order $order): array
    {
        return [
            'id' => $order->id,
            'price' => $order->price,
            'quantity' => $order->quantity,
            'filled_quantity' => $order->filled_quantity,
            'trade_type' => $order->trade_type,
            'type' => $order->type,
            'user_id' => $order->user_id,
            'timestamp' => $order->updated_at->timestamp ?? time(),
            'order' => $order,
        ];
    }

    /**
     * Check if order type is market.
     */
    private function isMarketType(string $type): bool
    {
        return $type === Consts::ORDER_TYPE_MARKET || $type === Consts::ORDER_TYPE_STOP_MARKET;
    }

    /**
     * Load all matchable orders from database.
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

        Log::info("HeapOrderBook: Loaded {$count} orders for {$this->coin}/{$this->currency}");

        return $count;
    }

    /**
     * Get order by ID.
     */
    public function getOrder(int $orderId): ?Order
    {
        return $this->orderIndex[$orderId]['order'] ?? null;
    }

    /**
     * Check if order exists in orderbook.
     */
    public function hasOrder(int $orderId): bool
    {
        return isset($this->orderIndex[$orderId]) && !isset($this->deletedIds[$orderId]);
    }

    /**
     * Get orderbook statistics.
     */
    public function getStats(): array
    {
        return [
            'currency' => $this->currency,
            'coin' => $this->coin,
            'buy_orders' => $this->buyHeap->count(),
            'sell_orders' => $this->sellHeap->count(),
            'total_indexed' => count($this->orderIndex),
            'pending_deletions' => count($this->deletedIds),
            'total_processed' => $this->totalOrders,
            'matched_pairs' => $this->matchedPairs,
            'best_bid' => $this->getBestBid(),
            'best_ask' => $this->getBestAsk(),
        ];
    }

    /**
     * Get best bid price.
     */
    public function getBestBid(): ?string
    {
        $top = $this->peekValidBuy();
        return $top['price'] ?? null;
    }

    /**
     * Get best ask price.
     */
    public function getBestAsk(): ?string
    {
        $top = $this->peekValidSell();
        return $top['price'] ?? null;
    }

    /**
     * Get the spread (difference between best ask and best bid).
     */
    public function getSpread(): ?string
    {
        $bestBid = $this->getBestBid();
        $bestAsk = $this->getBestAsk();

        if (!$bestBid || !$bestAsk) {
            return null;
        }

        return bcsub($bestAsk, $bestBid, 8);
    }

    /**
     * Clear the orderbook.
     */
    public function clear(): void
    {
        $this->buyHeap = new BuyOrderHeap();
        $this->sellHeap = new SellOrderHeap();
        $this->orderIndex = [];
        $this->deletedIds = [];
        $this->totalOrders = 0;
        $this->matchedPairs = 0;
    }
}
