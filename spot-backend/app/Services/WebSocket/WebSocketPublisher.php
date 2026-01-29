<?php

namespace App\Services\WebSocket;

use Illuminate\Support\Facades\Redis;

/**
 * Helper class to publish events to the WebSocket server via Redis.
 *
 * Usage:
 *   WebSocketPublisher::orderBook('BTC/USDT', $orderBookData);
 *   WebSocketPublisher::trade('BTC/USDT', $tradeData);
 *   WebSocketPublisher::ticker('BTC/USDT', $tickerData);
 *   WebSocketPublisher::userOrder($userId, $orderData);
 */
class WebSocketPublisher
{
    private const REDIS_CHANNEL_PREFIX = 'spot:ws:';

    /**
     * Publish order book update.
     */
    public static function orderBook(string $symbol, array $data): void
    {
        self::publish("orderbook:{$symbol}", $data);

        // Also cache the latest order book snapshot
        Redis::set("spot:orderbook:{$symbol}", json_encode($data));
    }

    /**
     * Publish trade execution.
     */
    public static function trade(string $symbol, array $data): void
    {
        self::publish("trades:{$symbol}", $data);
    }

    /**
     * Publish ticker update.
     */
    public static function ticker(string $symbol, array $data): void
    {
        self::publish("ticker:{$symbol}", $data);

        // Also cache the latest ticker
        Redis::set("spot:ticker:{$symbol}", json_encode($data));
    }

    /**
     * Publish kline/candlestick update.
     */
    public static function kline(string $symbol, string $interval, array $data): void
    {
        self::publish("kline:{$symbol}:{$interval}", $data);
    }

    /**
     * Publish user-specific order update.
     */
    public static function userOrder(int $userId, array $data): void
    {
        self::publish("user:{$userId}", [
            'event' => 'order',
            'data' => $data,
        ]);
    }

    /**
     * Publish user-specific balance update.
     */
    public static function userBalance(int $userId, array $data): void
    {
        self::publish("user:{$userId}", [
            'event' => 'balance',
            'data' => $data,
        ]);
    }

    /**
     * Publish user-specific trade notification.
     */
    public static function userTrade(int $userId, array $data): void
    {
        self::publish("user:{$userId}", [
            'event' => 'trade',
            'data' => $data,
        ]);
    }

    /**
     * Publish message to Redis channel.
     */
    private static function publish(string $channel, array $data): void
    {
        try {
            Redis::publish(
                self::REDIS_CHANNEL_PREFIX . $channel,
                json_encode($data)
            );
        } catch (\Exception $e) {
            \Log::error("WebSocketPublisher: Failed to publish to {$channel}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
