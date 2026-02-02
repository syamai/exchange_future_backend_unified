<?php

namespace App\Services\Cache;

use App\Models\Order;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Redis cache layer for orders to reduce DB queries (FR-PF-004)
 * Target: 80% reduction in database queries
 */
class OrderCacheService
{
    private const TTL = 3600;
    private const KEY_PREFIX = 'order:';

    private int $hits = 0;
    private int $misses = 0;

    public function getOrder(int $orderId): ?Order
    {
        $cached = $this->get($orderId);
        if (!$cached) {
            $this->misses++;
            return null;
        }
        $this->hits++;
        return $this->hydrateOrder($cached);
    }

    public function get(int $orderId): ?array
    {
        try {
            $data = Redis::hgetall($this->getKey($orderId));
            return empty($data) ? null : $this->deserialize($data);
        } catch (\Exception $e) {
            Log::warning("OrderCacheService: Failed to get order {$orderId}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function set(int $orderId, array $data): void
    {
        try {
            $key = $this->getKey($orderId);
            Redis::hmset($key, $this->serialize($data));
            Redis::expire($key, self::TTL);
        } catch (\Exception $e) {
            Log::warning("OrderCacheService: Failed to set order {$orderId}", ['error' => $e->getMessage()]);
        }
    }

    public function setOrder(Order $order): void
    {
        $this->set($order->id, [
            'id' => $order->id,
            'user_id' => $order->user_id,
            'currency' => $order->currency,
            'coin' => $order->coin,
            'price' => $order->price,
            'quantity' => $order->quantity,
            'filled_quantity' => $order->filled_quantity,
            'trade_type' => $order->trade_type,
            'type' => $order->type,
            'status' => $order->status,
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
        ]);
    }

    public function invalidate(int $orderId): void
    {
        try {
            Redis::del($this->getKey($orderId));
        } catch (\Exception $e) {
            Log::warning("OrderCacheService: Failed to invalidate order {$orderId}", ['error' => $e->getMessage()]);
        }
    }

    public function getStats(): array
    {
        $total = $this->hits + $this->misses;
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit_rate' => $total > 0 ? round(($this->hits / $total) * 100, 2) . '%' : '0%',
        ];
    }

    private function getKey(int $orderId): string
    {
        return self::KEY_PREFIX . $orderId;
    }

    private function serialize(array $data): array
    {
        unset($data['order']);
        return array_map(fn($v) => is_array($v) || is_object($v) ? json_encode($v) : (string)$v, $data);
    }

    private function deserialize(array $data): array
    {
        return array_map(function ($v) {
            $decoded = json_decode($v, true);
            return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : $v;
        }, $data);
    }

    private function hydrateOrder(array $data): Order
    {
        $order = new Order();
        $order->exists = true;
        foreach ($data as $key => $value) {
            if (in_array($key, ['created_at', 'updated_at']) && $value) {
                $order->{$key} = \Carbon\Carbon::parse($value);
            } else {
                $order->{$key} = $value;
            }
        }
        return $order;
    }
}
