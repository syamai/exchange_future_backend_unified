<?php

namespace Tests\Feature\OrderBook;

use Illuminate\Support\Facades\Artisan;
use App\Consts;
use App\Models\Order;
use App\Http\Services\OrderService;
use Tests\Feature\BaseTestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Cache;

class OrderBook0Test extends BaseTestCase
{
    use WithoutMiddleware;

    protected $tickerSize = 1;


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
        $price = 3114000;
        $this->setUpPrice($price);
        $this->setUpBalance(10000000, 1, 1000000, 1);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        Cache::flush();
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', $price, 1, 0.321);
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testGetOrderBook2()
    {
        $price = 3114000;
        $this->setUpPrice($price);
        $this->setUpBalance(10000000, 1, 10000000, 1);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        // $this->checkOrderBook('buy', $price, 1, 0.321);

        $price = 5650000;
        $this->setUpPrice($price);
        $count = Consts::MAX_ORDER_BOOK_SIZE + 1;
        for ($i = 0; $i < $count; $i++) {
            $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
                'price' => $price - $i * $this->tickerSize, 'quantity' => 0.02];
            $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);
        }

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', 3114000, 0, 0); // reload order book
        $this->checkOrderBook('buy', $price, 1, 0.02);
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testGetOrderBook3()
    {
        $price = 6114000;
        $this->setUpPrice($price);
        $this->setUpBalance(10000000, 1, 10000000, 1);

        $buyData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        // $this->checkOrderBook('buy', $price, 1, 0.321);

        $price = 5650000;
        $this->setUpPrice($price);
        $count = Consts::MAX_ORDER_BOOK_SIZE + 1;
        for ($i = 0; $i < $count; $i++) {
            $buyData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
                'price' => $price + $i * $this->tickerSize, 'quantity' => 0.02];
            $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);
        }

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('sell', 6114000, 0, 0); // reload order book
        $this->checkOrderBook('sell', $price, 1, 0.02);
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testCreateOrder1()
    {
        $price = 3114000;
        $this->setUpPrice($price);
        $this->setUpBalance(10000000, 1, 1000000, 1);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', $price, 1, 0.321);
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testCreateOrder2()
    {
        $price = 3114000;
        $this->setUpPrice($price);
        $this->setUpBalance(10000000, 1, 1000000, 1);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);
        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price + $this->tickerSize, 'quantity' => 1];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', $price, 1, 0.321);
        $this->checkOrderBook('sell', $price + $this->tickerSize, 1, 1);
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testCreateOrder3()
    {
        $price = 3114000;
        $this->setUpPrice($price);
        $this->setUpBalance(10000000, 1, 10000000, 1);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);
        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price + $this->tickerSize, 'quantity' => 1];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $buyData);

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', $price, 1, 0.321);
        $this->checkOrderBook('buy', $price + $this->tickerSize, 1, 1);
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testCreateOrder4()
    {
        $price = 3114000;
        $this->setUpPrice($price);
        $this->setUpBalance(10000000, 1, 10000000, 1);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);
        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 1];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $buyData);

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', $price, 2, 1.321);
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testCreateOrder5()
    {
        $price = 3114000;
        $this->setUpPrice($price);
        $this->setUpBalance(100000000, 1, 10000000, 1);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $count = Consts::MAX_ORDER_BOOK_SIZE + 2;
        $orderPrice = 0;
        for ($i = 0; $i < $count; $i++) {
            $orderPrice = $price - $i * $this->tickerSize;
            $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
                'price' => $orderPrice, 'quantity' => 0.2];
            $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);
        }

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', $orderPrice, 0, 0);
        $this->checkOrderBook('buy', $price, 1, 0.2);
        $this->checkOrderBook('buy', $price - 19 * $this->tickerSize, 1, 0.2);
    }

    /**
     * test market order
     * @group orderBook
     *
     * @return void
     */
    public function testCreateOrder6()
    {
        $price = 3114000;
        $this->setUpPrice($price);
        $this->setUpBalance(10000000, 1, 1000000, 1);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'market', 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', $price, 0, 0);
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testCancelOrder1()
    {
        $price = 3114000;
        $this->setUpPrice($price);
        $this->setUpBalance(10000000, 1, 10000000, 1);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);
        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 1];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $buyData);

        $orderId = Order::min('id');
        $this->actingAs($this->user1, 'api')->json('PUT', "/api/orders/$orderId/cancel");

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', $price, 1, 1); // first order is canceled
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testMatchOrder1()
    {
        $price = 3114000;
        $this->setUpPrice($price);
        $this->setUpBalance(10000000, 1, 10000000, 1);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);
        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 1];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        Artisan::call('order:process', [
            'currency' => $this->currency,
            'coin' => $this->coin,
        ]);
        Artisan::call('spot:update_orderbook');

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', $price, 0, 0); // first order is executed
        $this->checkOrderBook('sell', $price, 1, 1 - 0.321); // second order remaining
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testMatchOrder2()
    {
        $price = 3114000;
        $this->setUpPrice($price);
        $this->setUpBalance(10000000, 1, 10000000, 1);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => $price, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);
        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'market', 'quantity' => 1];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        Artisan::call('order:process', [
            'currency' => $this->currency,
            'coin' => $this->coin,
        ]);
        Artisan::call('spot:update_orderbook');

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', $price, 0, 0); // first order is executed
        $this->checkOrderBook('sell', $price, 0, 0); // doesn't count market order
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testActiveOrder1()
    {
        $price = 3114000;
        $this->setUpPrice($price);
        $this->setUpBalance(10000000, 1, 10000000, 1);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'stop_limit',
            'base_price' => 3116000, 'stop_condition' => 'ge', 'price' => 3117000, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => 3116000, 'quantity' => 1];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        Artisan::call('order:process', [
            'currency' => $this->currency,
            'coin' => $this->coin,
        ]);
        Artisan::call('spot:update_orderbook');

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', 3117000, 0, 0); // doesn't count stop limit
        $this->checkOrderBook('buy', 3116000, 1, 1);

        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => 3116000, 'quantity' => 0.321];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        Artisan::call('order:process', [
            'currency' => $this->currency,
            'coin' => $this->coin,
        ]);
        Artisan::call('spot:update_orderbook');

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', 3117000, 1, 0.321); // first order is activated
        $this->checkOrderBook('buy', 3116000, 1, 1 - 0.321); // second order is executed
        $this->checkOrderBook('sell', 3116000, 0, 0); // third order remaining
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testActiveOrder2()
    {
        $price = 3119000;
        $this->setUpPrice($price);
        $this->setUpBalance(1000000000, 10, 1000000000, 20);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => 3116000, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'stop_limit',
            'base_price' => 3116000, 'stop_condition' => 'le','price' => 3115000, 'quantity' => 1];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'stop_limit',
            'base_price' => 3116000, 'stop_condition' => 'le','price' => 3114000, 'quantity' => 1];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        Artisan::call('order:process', [
            'currency' => $this->currency,
            'coin' => $this->coin,
        ]);
        Artisan::call('spot:update_orderbook');

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', 3116000, 1, 0.321);
        $this->checkOrderBook('buy', 3115000, 0, 0); // doesn't count stop limit
        $this->checkOrderBook('buy', 3114000, 0, 0); // doesn't count stop limit

        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit', 'price' => 3116000, 'quantity' => 0.321];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        Artisan::call('order:process', [
            'currency' => $this->currency,
            'coin' => $this->coin,
        ]);
        Artisan::call('spot:update_orderbook');

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', 3116000, 0, 0); // first order is executed
        $this->checkOrderBook('sell', 3115000, 1, 1); // second order is executed
        $this->checkOrderBook('sell', 3114000, 1, 1); // third order is executed
        $this->checkOrderBook('sell', 3116000, 0, 0); // fourth order is executed
    }

    /**
     * test
     * @group orderBook
     *
     * @return void
     */
    public function testActiveOrder3()
    {
        $price = 311900000;
        $this->setUpPrice($price);
        $this->setUpBalance(1000000000000, 10, 1000000000000, 20);
        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);

        $count = Consts::ORDER_BOOK_SIZE * 10 + 1;
        for ($i = 0; $i < $count; $i++) {
            $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'stop_limit',
                'base_price' => 500000000, 'stop_condition' => 'ge', 'price' => 500050000, 'quantity' => 0.001];
            $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);
            sleep(0.01);
        }

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => 500000000, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        Artisan::call('order:process', [
            'currency' => $this->currency,
            'coin' => $this->coin,
        ]);
        Artisan::call('spot:update_orderbook');

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', 500000000, 1, 0.321);

        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => 5000000, 'quantity' => 0.321];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        Artisan::call('order:process', [
            'currency' => $this->currency,
            'coin' => $this->coin,
        ]);
        Artisan::call('spot:update_orderbook');

        $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize);
        $this->checkOrderBook('buy', 500050000, $count, $count * 0.001);
        $this->checkOrderBook('sell', 500000000, 0, 0);
    }
}
