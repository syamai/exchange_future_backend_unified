<?php

namespace Tests\Feature\OrderBook;

use App\Consts;
use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Http\Services\OrderService;
use App\Http\Services\PriceService;
use Tests\Feature\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderBook2Test extends BaseTestCase
{
    use WithoutMiddleware;

    protected $tickerSize = 0.00001;

    public function setUp(): void
    {
        parent::setUp();
        $this->currency = 'btc';
        $this->coin = 'eth';
    }

    private function getBaseId()
    {
        return Order::min('id') - 1;
    }

    private function checkOrderBook($tradeType, $price, $count, $quantity)
    {
        $key = OrderService::getOrderBookKey($this->currency, $this->coin, $this->tickerSize);
        $orderBook = Cache::get($key);
        // var_dump($orderBook);
        $this->assertTrue($orderBook != null);

        $subOrderBook = collect($orderBook[$tradeType]);
        $row = $subOrderBook->filter(function ($value, $key) use ($price) {
            return $value->price == $price;
        })->first();
        // if ($count) {
        //     $this->assertTrue($row != null);
        // }
        if (!$row) {
            return;
        }
        // $this->assertEquals($row->count, $count);
        $this->assertEquals($row->quantity, $quantity);
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testGetOrderBook1()
    {
        $price = 0.01769;
        $this->setUpPrice($price);
        $this->setUpBalance(100, 100, 100, 100);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 1.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        Cache::flush();
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', 0.01769, 1, 1.321);
    }

        /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testCreateOrder1()
    {
        $price = 0.01769;
        $this->setUpPrice($price);
        $this->setUpBalance(100, 100, 100, 100);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $buyData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 1.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('sell', 0.01769, 1, 1.321);
    }
}
