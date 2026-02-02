<?php

namespace App\Services\Buffer;

/**
 * Factory for creating WriteBuffer instances.
 *
 * Automatically selects the appropriate implementation based on environment:
 * - Production + Swoole: WriteBuffer (async batch)
 * - Testing/Debug: SyncWriteBuffer (immediate writes)
 */
class WriteBufferFactory
{
    /**
     * Create appropriate WriteBuffer instance for current environment.
     */
    public static function create(): WriteBufferInterface
    {
        // Use sync buffer in testing environment
        if (app()->environment('testing') || env('WRITE_BUFFER_SYNC', false)) {
            return new SyncWriteBuffer();
        }

        // Use async buffer in production
        return new WriteBuffer(
            maxBufferSize: (int) env('WRITE_BUFFER_MAX_SIZE', 100),
            flushIntervalMs: (int) env('WRITE_BUFFER_FLUSH_INTERVAL_MS', 500),
            maxRetries: (int) env('WRITE_BUFFER_MAX_RETRIES', 3)
        );
    }

    /**
     * Create sync buffer (for testing).
     */
    public static function createSync(): SyncWriteBuffer
    {
        return new SyncWriteBuffer();
    }

    /**
     * Create async buffer with custom configuration.
     */
    public static function createAsync(
        int $maxBufferSize = 100,
        int $flushIntervalMs = 500,
        int $maxRetries = 3
    ): WriteBuffer {
        return new WriteBuffer($maxBufferSize, $flushIntervalMs, $maxRetries);
    }
}
