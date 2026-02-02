<?php

namespace Tests\Unit\Services\Buffer;

use App\Services\Buffer\WriteBuffer;
use App\Services\Buffer\WriteBufferFactory;
use App\Services\Buffer\SyncWriteBuffer;
use Tests\TestCase;

/**
 * Unit tests for WriteBufferFactory class.
 */
class WriteBufferFactoryTest extends TestCase
{
    /** @test */
    public function it_creates_sync_buffer_in_testing_environment(): void
    {
        // Testing environment should return SyncWriteBuffer
        $buffer = WriteBufferFactory::create();

        $this->assertInstanceOf(SyncWriteBuffer::class, $buffer);
    }

    /** @test */
    public function it_creates_sync_buffer_explicitly(): void
    {
        $buffer = WriteBufferFactory::createSync();

        $this->assertInstanceOf(SyncWriteBuffer::class, $buffer);
    }

    /** @test */
    public function it_creates_async_buffer_with_custom_config(): void
    {
        $buffer = WriteBufferFactory::createAsync(
            maxBufferSize: 200,
            flushIntervalMs: 1000,
            maxRetries: 5
        );

        $this->assertInstanceOf(WriteBuffer::class, $buffer);

        $stats = $buffer->getStats();
        $this->assertEquals(200, $stats['config']['max_buffer_size']);
        $this->assertEquals(1000, $stats['config']['flush_interval_ms']);
        $this->assertEquals(5, $stats['config']['max_retries']);
    }

    /** @test */
    public function sync_buffer_has_correct_stats_structure(): void
    {
        $buffer = WriteBufferFactory::createSync();
        $stats = $buffer->getStats();

        $this->assertArrayHasKey('buffer', $stats);
        $this->assertArrayHasKey('config', $stats);
        $this->assertArrayHasKey('totals', $stats);
        $this->assertEquals('synchronous', $stats['config']['mode']);
    }

    /** @test */
    public function async_buffer_has_correct_stats_structure(): void
    {
        $buffer = WriteBufferFactory::createAsync();
        $stats = $buffer->getStats();

        $this->assertArrayHasKey('buffer', $stats);
        $this->assertArrayHasKey('config', $stats);
        $this->assertArrayHasKey('totals', $stats);
        $this->assertArrayHasKey('max_buffer_size', $stats['config']);
    }
}
