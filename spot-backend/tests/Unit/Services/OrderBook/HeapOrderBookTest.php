<?php

namespace Tests\Unit\Services\OrderBook;

use App\Consts;
use App\Models\Order;
use App\Services\OrderBook\BuyOrderHeap;
use App\Services\OrderBook\HeapOrderBook;
use App\Services\OrderBook\SellOrderHeap;
use Tests\TestCase;
use Mockery;

class HeapOrderBookTest extends TestCase
{
    private HeapOrderBook $orderBook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderBook = new HeapOrderBook('usdt', 'btc');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_sorts_buy_orders_by_price_descending(): void
    {
        // Arrange
        $orders = [
            $this->createMockOrder(1, '100.00', Consts::ORDER_TRADE_TYPE_BUY),
            $this->createMockOrder(2, '105.00', Consts::ORDER_TRADE_TYPE_BUY),
            $this->createMockOrder(3, '102.00', Consts::ORDER_TRADE_TYPE_BUY),
        ];

        // Act
        foreach ($orders as $order) {
            $this->orderBook->addOrder($order);
        }

        // Assert - highest price should be best bid
        $this->assertEquals('105.00', $this->orderBook->getBestBid());
    }

    /** @test */
    public function it_sorts_sell_orders_by_price_ascending(): void
    {
        // Arrange
        $orders = [
            $this->createMockOrder(1, '100.00', Consts::ORDER_TRADE_TYPE_SELL),
            $this->createMockOrder(2, '95.00', Consts::ORDER_TRADE_TYPE_SELL),
            $this->createMockOrder(3, '98.00', Consts::ORDER_TRADE_TYPE_SELL),
        ];

        // Act
        foreach ($orders as $order) {
            $this->orderBook->addOrder($order);
        }

        // Assert - lowest price should be best ask
        $this->assertEquals('95.00', $this->orderBook->getBestAsk());
    }

    /** @test */
    public function it_matches_orders_when_buy_price_gte_sell_price(): void
    {
        // Arrange
        $buyOrder = $this->createMockOrder(1, '100.00', Consts::ORDER_TRADE_TYPE_BUY);
        $sellOrder = $this->createMockOrder(2, '99.00', Consts::ORDER_TRADE_TYPE_SELL);

        $this->orderBook->addOrder($buyOrder);
        $this->orderBook->addOrder($sellOrder);

        // Act
        $pair = $this->orderBook->getMatchablePair();

        // Assert
        $this->assertNotNull($pair);
        $this->assertEquals(1, $pair['buy']->id);
        $this->assertEquals(2, $pair['sell']->id);
    }

    /** @test */
    public function it_does_not_match_when_buy_price_lt_sell_price(): void
    {
        // Arrange
        $buyOrder = $this->createMockOrder(1, '98.00', Consts::ORDER_TRADE_TYPE_BUY);
        $sellOrder = $this->createMockOrder(2, '100.00', Consts::ORDER_TRADE_TYPE_SELL);

        $this->orderBook->addOrder($buyOrder);
        $this->orderBook->addOrder($sellOrder);

        // Act
        $pair = $this->orderBook->getMatchablePair();

        // Assert
        $this->assertNull($pair);
    }

    /** @test */
    public function it_removes_order_using_lazy_deletion(): void
    {
        // Arrange
        $order = $this->createMockOrder(1, '100.00', Consts::ORDER_TRADE_TYPE_BUY);
        $this->orderBook->addOrder($order);

        // Act
        $result = $this->orderBook->removeOrder(1);

        // Assert
        $this->assertTrue($result);
        $this->assertFalse($this->orderBook->hasOrder(1));
    }

    /** @test */
    public function it_respects_time_priority_for_same_price(): void
    {
        // Arrange - create orders with same price but different times
        $order1 = $this->createMockOrder(1, '100.00', Consts::ORDER_TRADE_TYPE_BUY, time() - 10);
        $order2 = $this->createMockOrder(2, '100.00', Consts::ORDER_TRADE_TYPE_BUY, time());

        $this->orderBook->addOrder($order2); // Add later order first
        $this->orderBook->addOrder($order1); // Add earlier order second

        $sellOrder = $this->createMockOrder(3, '100.00', Consts::ORDER_TRADE_TYPE_SELL);
        $this->orderBook->addOrder($sellOrder);

        // Act
        $pair = $this->orderBook->getMatchablePair();

        // Assert - earlier order should match first (FIFO)
        $this->assertNotNull($pair);
        $this->assertEquals(1, $pair['buy']->id);
    }

    /** @test */
    public function it_calculates_spread_correctly(): void
    {
        // Arrange
        $buyOrder = $this->createMockOrder(1, '99.00', Consts::ORDER_TRADE_TYPE_BUY);
        $sellOrder = $this->createMockOrder(2, '101.00', Consts::ORDER_TRADE_TYPE_SELL);

        $this->orderBook->addOrder($buyOrder);
        $this->orderBook->addOrder($sellOrder);

        // Act
        $spread = $this->orderBook->getSpread();

        // Assert
        $this->assertEquals('2.00000000', $spread);
    }

    /** @test */
    public function it_returns_correct_stats(): void
    {
        // Arrange
        $this->orderBook->addOrder($this->createMockOrder(1, '100.00', Consts::ORDER_TRADE_TYPE_BUY));
        $this->orderBook->addOrder($this->createMockOrder(2, '99.00', Consts::ORDER_TRADE_TYPE_BUY));
        $this->orderBook->addOrder($this->createMockOrder(3, '101.00', Consts::ORDER_TRADE_TYPE_SELL));

        // Act
        $stats = $this->orderBook->getStats();

        // Assert
        $this->assertEquals('usdt', $stats['currency']);
        $this->assertEquals('btc', $stats['coin']);
        $this->assertEquals(2, $stats['buy_orders']);
        $this->assertEquals(1, $stats['sell_orders']);
    }

    /** @test */
    public function it_clears_orderbook(): void
    {
        // Arrange
        $this->orderBook->addOrder($this->createMockOrder(1, '100.00', Consts::ORDER_TRADE_TYPE_BUY));
        $this->orderBook->addOrder($this->createMockOrder(2, '101.00', Consts::ORDER_TRADE_TYPE_SELL));

        // Act
        $this->orderBook->clear();

        // Assert
        $stats = $this->orderBook->getStats();
        $this->assertEquals(0, $stats['buy_orders']);
        $this->assertEquals(0, $stats['sell_orders']);
    }

    /**
     * Create a mock Order for testing.
     */
    private function createMockOrder(int $id, string $price, string $tradeType, ?int $timestamp = null): Order
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = $id;
        $order->price = $price;
        $order->quantity = '1.00000000';
        $order->filled_quantity = '0.00000000';
        $order->trade_type = $tradeType;
        $order->type = Consts::ORDER_TYPE_LIMIT;
        $order->user_id = 1;
        $order->status = Consts::ORDER_STATUS_PENDING;
        $order->updated_at = now()->setTimestamp($timestamp ?? time());

        $order->shouldReceive('canMatching')->andReturn(true);
        $order->shouldReceive('fresh')->andReturn($order);

        return $order;
    }
}
