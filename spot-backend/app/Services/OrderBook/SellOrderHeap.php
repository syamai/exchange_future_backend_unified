<?php

namespace App\Services\OrderBook;

use SplHeap;

/**
 * MinHeap for sell orders - lowest price first (FR-DS-001)
 * Time complexity: O(log n) insert, O(1) peek, O(log n) extract
 */
class SellOrderHeap extends SplHeap
{
    /**
     * Compare two orders for heap ordering.
     * Returns positive if $a should be higher in heap (higher priority).
     *
     * Priority: Lower price first, then earlier time (FIFO for same price)
     */
    protected function compare($a, $b): int
    {
        // Lower price = higher priority (should be at top)
        // Reverse comparison for MinHeap behavior
        $priceCompare = bccomp($b['price'], $a['price'], 8);

        if ($priceCompare !== 0) {
            return $priceCompare;
        }

        // Same price: earlier timestamp = higher priority
        return $b['timestamp'] <=> $a['timestamp'];
    }
}
