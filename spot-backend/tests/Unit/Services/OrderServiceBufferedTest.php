<?php

namespace Tests\Unit\Services;

use App\Consts;
use App\Http\Services\OrderService;
use App\Models\Order;
use App\Services\Buffer\BufferedMatchingService;
use App\Services\Buffer\SyncWriteBuffer;
use Tests\TestCase;
use Mockery;

/**
 * Unit tests for OrderService buffered matching integration.
 */
class OrderServiceBufferedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Set env for buffered writes
        putenv('USE_BUFFERED_WRITES=true');
    }

    protected function tearDown(): void
    {
        putenv('USE_BUFFERED_WRITES=false');
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_enables_buffered_writes_when_env_is_set(): void
    {
        $service = new OrderService();

        $this->assertTrue($service->isBufferedWritesEnabled());
    }

    /** @test */
    public function it_disables_buffered_writes_when_env_is_not_set(): void
    {
        putenv('USE_BUFFERED_WRITES=false');

        $service = new OrderService();

        $this->assertFalse($service->isBufferedWritesEnabled());
    }

    /** @test */
    public function it_accepts_custom_buffered_matching_service(): void
    {
        $customBuffer = new SyncWriteBuffer();
        $customService = new BufferedMatchingService($customBuffer);

        $service = new OrderService($customService);

        $this->assertTrue($service->isBufferedWritesEnabled());
    }

    /** @test */
    public function it_returns_null_for_stats_when_disabled(): void
    {
        putenv('USE_BUFFERED_WRITES=false');

        $service = new OrderService();

        $this->assertNull($service->getBufferedMatchingStats());
    }

    /** @test */
    public function it_returns_stats_when_enabled(): void
    {
        $service = new OrderService();

        $stats = $service->getBufferedMatchingStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('match_count', $stats);
        $this->assertArrayHasKey('buffer', $stats);
    }

    /** @test */
    public function it_returns_null_for_flush_when_disabled(): void
    {
        putenv('USE_BUFFERED_WRITES=false');

        $service = new OrderService();

        $this->assertNull($service->flushBufferedWrites());
    }

    /** @test */
    public function it_flushes_when_enabled(): void
    {
        $service = new OrderService();

        $result = $service->flushBufferedWrites();

        $this->assertNotNull($result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(0, $result->getTotalWritten()); // Nothing buffered yet
    }

    /** @test */
    public function it_throws_when_buffered_match_called_without_enabled(): void
    {
        putenv('USE_BUFFERED_WRITES=false');

        $service = new OrderService();
        $buyOrder = $this->createMockOrder(1, 'buy', 'limit', '100', '10', '0');
        $sellOrder = $this->createMockOrder(2, 'sell', 'limit', '50', '10', '0');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('BufferedMatchingService is not enabled');

        $service->matchOrdersBuffered($buyOrder, $sellOrder, '10', '50', '0', '0', true);
    }

    /** @test */
    public function it_buffers_match_when_enabled(): void
    {
        // Use WriteBuffer (non-sync) which doesn't immediately write to DB
        $customBuffer = new \App\Services\Buffer\WriteBuffer(100, 500, 3);
        $customService = new BufferedMatchingService($customBuffer);
        $service = new OrderService($customService);

        $buyOrder = $this->createMockOrder(1, 'buy', 'limit', '100', '10', '0');
        $sellOrder = $this->createMockOrder(2, 'sell', 'limit', '50', '10', '0');

        $tradeData = $service->matchOrdersBuffered(
            $buyOrder,
            $sellOrder,
            '10.00',
            '50',
            '0.5',
            '0.25',
            true
        );

        $this->assertEquals(1, $tradeData['buyer_id']);
        $this->assertEquals(2, $tradeData['seller_id']);
        $this->assertEquals('50', $tradeData['quantity']);
        $this->assertEquals('10.00', $tradeData['price']);
    }

    /** @test */
    public function it_increments_stats_after_buffered_match(): void
    {
        // Use WriteBuffer (non-sync) which doesn't immediately write to DB
        $customBuffer = new \App\Services\Buffer\WriteBuffer(100, 500, 3);
        $customService = new BufferedMatchingService($customBuffer);
        $service = new OrderService($customService);

        $buyOrder = $this->createMockOrder(1, 'buy', 'limit', '100', '10', '0');
        $sellOrder = $this->createMockOrder(2, 'sell', 'limit', '50', '10', '0');

        $service->matchOrdersBuffered($buyOrder, $sellOrder, '10', '50', '0', '0', true);

        $stats = $service->getBufferedMatchingStats();
        $this->assertEquals(1, $stats['match_count']);
    }

    /** @test */
    public function it_throws_when_match_with_buffering_called_without_enabled(): void
    {
        putenv('USE_BUFFERED_WRITES=false');

        $service = new OrderService();
        $buyOrder = $this->createMockOrder(1, 'buy', 'limit', '100', '10', '0');
        $sellOrder = $this->createMockOrder(2, 'sell', 'limit', '50', '10', '0');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('BufferedMatchingService is not enabled');

        $service->matchOrdersWithBuffering($buyOrder, $sellOrder, true);
    }

    /**
     * Create a mock Order object for testing.
     */
    private function createMockOrder(
        int $id,
        string $tradeType,
        string $type,
        string $quantity,
        string $price,
        string $executedQuantity
    ): Order {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = $id;
        $order->user_id = $id;
        $order->trade_type = $tradeType;
        $order->type = $type;
        $order->quantity = $quantity;
        $order->price = $price;
        $order->executed_quantity = $executedQuantity;
        $order->fee = '0';
        $order->currency = 'usd';
        $order->coin = 'btc';

        $order->shouldReceive('getRemaining')->andReturn(
            bcsub($quantity, $executedQuantity, 18)
        );

        return $order;
    }
}
