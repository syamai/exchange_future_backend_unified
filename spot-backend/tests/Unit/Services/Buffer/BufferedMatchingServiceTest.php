<?php

namespace Tests\Unit\Services\Buffer;

use App\Consts;
use App\Models\Order;
use App\Services\Buffer\BufferedMatchingService;
use App\Services\Buffer\SyncWriteBuffer;
use App\Services\Buffer\WriteBuffer;
use Tests\TestCase;
use Mockery;

/**
 * Unit tests for BufferedMatchingService.
 */
class BufferedMatchingServiceTest extends TestCase
{
    private BufferedMatchingService $service;
    private WriteBuffer $mockBuffer;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a real WriteBuffer for testing (not SyncWriteBuffer)
        $this->mockBuffer = new WriteBuffer(100, 500, 3);
        $this->service = new BufferedMatchingService($this->mockBuffer);
    }

    /** @test */
    public function it_creates_with_default_buffer(): void
    {
        $service = new BufferedMatchingService();

        // In testing environment, should use SyncWriteBuffer
        $this->assertInstanceOf(SyncWriteBuffer::class, $service->getWriteBuffer());
    }

    /** @test */
    public function it_creates_with_custom_buffer(): void
    {
        $customBuffer = new WriteBuffer(50, 250, 2);
        $service = new BufferedMatchingService($customBuffer);

        $this->assertSame($customBuffer, $service->getWriteBuffer());
    }

    /** @test */
    public function it_buffers_order_updates(): void
    {
        $buyOrder = $this->createMockOrder(1, 'buy', 'limit', '100', '10', '0');
        $sellOrder = $this->createMockOrder(2, 'sell', 'limit', '50', '10', '0');

        $tradeData = $this->service->bufferMatch(
            $buyOrder,
            $sellOrder,
            '10.00',   // price
            '50',      // quantity
            '0.5',     // buyFee
            '0.25',    // sellFee
            true       // isBuyerMaker
        );

        // Verify trade data structure
        $this->assertEquals(1, $tradeData['buyer_id']);
        $this->assertEquals(2, $tradeData['seller_id']);
        $this->assertEquals(1, $tradeData['buy_order_id']);
        $this->assertEquals(2, $tradeData['sell_order_id']);
        $this->assertEquals('50', $tradeData['quantity']);
        $this->assertEquals('10.00', $tradeData['price']);
        $this->assertEquals('0.5', $tradeData['buy_fee']);
        $this->assertEquals('0.25', $tradeData['sell_fee']);
        $this->assertEquals(1, $tradeData['is_buyer_maker']);
    }

    /** @test */
    public function it_increments_match_count(): void
    {
        $buyOrder = $this->createMockOrder(1, 'buy', 'limit', '100', '10', '0');
        $sellOrder = $this->createMockOrder(2, 'sell', 'limit', '50', '10', '0');

        $this->service->bufferMatch($buyOrder, $sellOrder, '10', '25', '0', '0', true);
        $this->service->bufferMatch($buyOrder, $sellOrder, '10', '25', '0', '0', true);

        $stats = $this->service->getStats();
        $this->assertEquals(2, $stats['match_count']);
    }

    /** @test */
    public function it_calculates_order_status_correctly(): void
    {
        // Partial fill: 50 of 100
        $buyOrder = $this->createMockOrder(1, 'buy', 'limit', '100', '10', '0');
        $sellOrder = $this->createMockOrder(2, 'sell', 'limit', '50', '10', '0');

        $this->service->bufferMatch($buyOrder, $sellOrder, '10', '50', '0', '0', true);

        $stats = $this->mockBuffer->getStats();
        // Should have 2 order updates buffered
        $this->assertEquals(2, $stats['buffer']['orders']);
    }

    /** @test */
    public function it_buffers_trades(): void
    {
        $buyOrder = $this->createMockOrder(1, 'buy', 'limit', '100', '10', '0');
        $sellOrder = $this->createMockOrder(2, 'sell', 'limit', '50', '10', '0');

        $this->service->bufferMatch($buyOrder, $sellOrder, '10', '50', '0', '0', true);

        $stats = $this->mockBuffer->getStats();
        $this->assertEquals(1, $stats['buffer']['trades']);
    }

    /** @test */
    public function it_buffers_balance_changes(): void
    {
        $buyOrder = $this->createMockOrder(1, 'buy', 'limit', '100', '10', '0');
        $sellOrder = $this->createMockOrder(2, 'sell', 'limit', '50', '10', '0');

        $this->service->bufferMatch($buyOrder, $sellOrder, '10', '50', '0.5', '0.25', true);

        $stats = $this->mockBuffer->getStats();
        // Should have balance updates for both users (buyer and seller, multiple currencies)
        $this->assertGreaterThan(0, $stats['buffer']['balances']);
    }

    /** @test */
    public function it_provides_stats(): void
    {
        $stats = $this->service->getStats();

        $this->assertArrayHasKey('match_count', $stats);
        $this->assertArrayHasKey('buffer', $stats);
        $this->assertEquals(0, $stats['match_count']);
    }

    /** @test */
    public function it_resets_match_count(): void
    {
        $buyOrder = $this->createMockOrder(1, 'buy', 'limit', '100', '10', '0');
        $sellOrder = $this->createMockOrder(2, 'sell', 'limit', '50', '10', '0');

        $this->service->bufferMatch($buyOrder, $sellOrder, '10', '50', '0', '0', true);
        $this->assertEquals(1, $this->service->getStats()['match_count']);

        $this->service->resetMatchCount();
        $this->assertEquals(0, $this->service->getStats()['match_count']);
    }

    /** @test */
    public function it_flushes_buffer(): void
    {
        // Note: This test doesn't actually write to DB since we're using mock data
        // It just verifies the flush mechanism works
        $result = $this->service->flush();

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(0, $result->getTotalWritten()); // Nothing buffered yet
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
        $order->user_id = $id; // Use same as ID for simplicity
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
