<?php

namespace Tests\Unit\Services\Buffer;

use App\Services\Buffer\WriteBuffer;
use App\Services\Buffer\FlushResult;
use Tests\TestCase;

/**
 * Unit tests for WriteBuffer class.
 *
 * Tests buffering logic without database interaction.
 */
class WriteBufferTest extends TestCase
{
    private WriteBuffer $buffer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buffer = new WriteBuffer(
            maxBufferSize: 10,
            flushIntervalMs: 500,
            maxRetries: 3
        );
    }

    /** @test */
    public function it_adds_orders_to_buffer(): void
    {
        $this->buffer->addOrder(1, ['status' => 'executed', 'executed_quantity' => '100']);
        $this->buffer->addOrder(2, ['status' => 'executing', 'executed_quantity' => '50']);

        $stats = $this->buffer->getStats();
        $this->assertEquals(2, $stats['buffer']['orders']);
        $this->assertEquals(0, $stats['buffer']['trades']);
    }

    /** @test */
    public function it_merges_multiple_updates_to_same_order(): void
    {
        // First update
        $this->buffer->addOrder(1, ['status' => 'executing', 'executed_quantity' => '50']);

        // Second update to same order
        $this->buffer->addOrder(1, ['status' => 'executed', 'executed_quantity' => '100']);

        $stats = $this->buffer->getStats();
        // Should still be 1 order (merged)
        $this->assertEquals(1, $stats['buffer']['orders']);
    }

    /** @test */
    public function it_adds_trades_to_buffer(): void
    {
        $this->buffer->addTrade(['buy_order_id' => 1, 'sell_order_id' => 2, 'quantity' => '100']);
        $this->buffer->addTrade(['buy_order_id' => 3, 'sell_order_id' => 4, 'quantity' => '200']);

        $stats = $this->buffer->getStats();
        $this->assertEquals(2, $stats['buffer']['trades']);
    }

    /** @test */
    public function it_adds_balance_updates_to_buffer(): void
    {
        $this->buffer->addBalanceUpdate(1, 'usd', ['available_balance' => '-100.00']);
        $this->buffer->addBalanceUpdate(2, 'btc', ['available_balance' => '0.5']);

        $stats = $this->buffer->getStats();
        $this->assertEquals(2, $stats['buffer']['balances']);
    }

    /** @test */
    public function it_merges_balance_updates_for_same_user_currency(): void
    {
        // First update
        $this->buffer->addBalanceUpdate(1, 'usd', ['available_balance' => '-100.00']);

        // Second update to same user+currency
        $this->buffer->addBalanceUpdate(1, 'usd', ['available_balance' => '-50.00']);

        $stats = $this->buffer->getStats();
        // Should still be 1 balance update (merged)
        $this->assertEquals(1, $stats['buffer']['balances']);
    }

    /** @test */
    public function it_should_flush_when_buffer_reaches_max_size(): void
    {
        // Add items up to max size
        for ($i = 1; $i <= 10; $i++) {
            $this->buffer->addOrder($i, ['status' => 'executed']);
        }

        $this->assertTrue($this->buffer->shouldFlush());
    }

    /** @test */
    public function it_should_not_flush_when_buffer_is_below_max_size(): void
    {
        $this->buffer->addOrder(1, ['status' => 'executed']);
        $this->buffer->addOrder(2, ['status' => 'executed']);

        // Reset flush timer to avoid time-based flush
        $this->buffer->setLastFlushTime(microtime(true) * 1000);

        $this->assertFalse($this->buffer->shouldFlush());
    }

    /** @test */
    public function it_should_flush_when_interval_elapsed_with_data(): void
    {
        $this->buffer->addOrder(1, ['status' => 'executed']);

        // Simulate time passing (set last flush to 1 second ago)
        $this->buffer->setLastFlushTime((microtime(true) * 1000) - 1000);

        $this->assertTrue($this->buffer->shouldFlush());
    }

    /** @test */
    public function it_clears_buffer(): void
    {
        $this->buffer->addOrder(1, ['status' => 'executed']);
        $this->buffer->addTrade(['buy_order_id' => 1, 'sell_order_id' => 2]);
        $this->buffer->addBalanceUpdate(1, 'usd', ['available_balance' => '-100']);

        $this->buffer->clear();

        $stats = $this->buffer->getStats();
        $this->assertEquals(0, $stats['buffer']['total']);
    }

    /** @test */
    public function it_returns_empty_result_when_nothing_to_flush(): void
    {
        $result = $this->buffer->flush();

        $this->assertEquals(0, $result->getTotalWritten());
        $this->assertTrue($result->isSuccess());
    }

    /** @test */
    public function flush_result_provides_correct_statistics(): void
    {
        $result = new FlushResult(
            ordersWritten: 5,
            tradesWritten: 3,
            balancesWritten: 2,
            durationMs: 15.5,
            errors: []
        );

        $this->assertEquals(5, $result->getOrdersWritten());
        $this->assertEquals(3, $result->getTradesWritten());
        $this->assertEquals(2, $result->getBalancesWritten());
        $this->assertEquals(10, $result->getTotalWritten());
        $this->assertEquals(15.5, $result->getDurationMs());
        $this->assertTrue($result->isSuccess());
    }

    /** @test */
    public function flush_result_marks_failure_with_errors(): void
    {
        $result = new FlushResult(
            ordersWritten: 0,
            tradesWritten: 0,
            balancesWritten: 0,
            durationMs: 0,
            errors: ['Database connection failed']
        );

        $this->assertFalse($result->isSuccess());
        $this->assertCount(1, $result->getErrors());
    }

    /** @test */
    public function it_tracks_statistics_correctly(): void
    {
        $buffer = new WriteBuffer(100, 500, 3);

        // Verify initial stats
        $stats = $buffer->getStats();
        $this->assertEquals(100, $stats['config']['max_buffer_size']);
        $this->assertEquals(500, $stats['config']['flush_interval_ms']);
        $this->assertEquals(3, $stats['config']['max_retries']);
        $this->assertEquals(0, $stats['totals']['flushes']);
    }
}
