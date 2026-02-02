<?php

namespace App\Services\OrderBook;

use SplHeap;

/**
 * MaxHeap for buy orders - highest price first (FR-DS-001)
 * Time complexity: O(log n) insert, O(1) peek, O(log n) extract
 */
class BuyOrderHeap extends SplHeap
{
    /**
     * Compare two orders for heap ordering.
     * Returns positive if $a should be higher in heap (higher priority).
     *
     * Priority: Higher price first, then earlier time (FIFO for same price)
     */
    protected function compare($a, $b): int
    {
        // Higher price = higher priority (should be at top)
        $priceCompare = bccomp($a['price'], $b['price'], 8);

        if ($priceCompare !== 0) {
            return $priceCompare;
        }

        // Same price: earlier timestamp = higher priority
        // Return positive if $a is earlier (higher priority)
        return $b['timestamp'] <=> $a['timestamp'];
    }
}
