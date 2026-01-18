<?php
namespace Tests\Feature;

use App\Consts;
use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Http\Services\OrderService;
use App\Http\Services\PriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PriceTest extends BaseTestCase
{

    private function getBaseId()
    {
        return Order::min('id') - 1;
    }

    private function checkPrice($price)
    {
        $currentPrice = $this->priceService->getPrice($this->currency, $this->coin);
        $this->assertEquals($currentPrice->price, $price);
    }

    /**
     * test limit orders
     * @group price
     *
     * @return void
     */
    public function test1()
    {
        $this->setUpBalance(10000000, 1, 10000000, 1);
        $this->setUpPrice(3000000);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => 3114500, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => 3114500, 'quantity' => 0.321];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        $this->checkPrice(3114500);
    }

    /**
     * test limit orders
     * @group price
     *
     * @return void
     */
    public function test2()
    {
        $this->setUpBalance(10000000, 1, 1000000, 1);
        $this->setUpPrice(3000000);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit', 'price' => 3115500, 'quantity' => 1];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => 3114500, 'quantity' => 0.321];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        $this->checkPrice(3115500);
    }

    /**
     * test market order
     * @group price
     *
     * @return void
     */
    public function test3()
    {
        $this->setUpBalance(10000000, 1, 10000000, 1);
        $this->setUpPrice(1000000);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'market', 'quantity' => 1];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => 3114500, 'quantity' => 0.321];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        $this->checkPrice(3114500);
    }

    /**
     * test market order
     * @group price
     *
     * @return void
     */
    public function test4()
    {
        $this->setUpBalance(10000000, 1, 10000000, 1);
        $this->setUpPrice(1000000);

        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'market', 'quantity' => 1];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit',
            'price' => 3114500, 'quantity' => 0.321];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $this->checkPrice(3114500);
    }

    /**
     * test market order
     * @group price
     *
     * @return void
     */
    public function test5()
    {
        $this->setUpBalance(10000000, 1, 10000000, 1);
        $this->setUpPrice(1000000);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'market', 'quantity' => 1];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'market', 'quantity' => 0.321];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);

        $this->checkPrice(1000000); //price should not be changed
    }

    /**
     * test market order
     * @group price
     *
     * @return void
     */
    public function test6()
    {
        $this->setUpBalance(10000000, 1, 10000000, 1);
        $this->setUpPrice(1000000);

        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'market', 'quantity' => 1];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $sellData = ['trade_type' => 'sell', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'market', 'quantity' => 0.321];
        $this->actingAs($this->user2, 'api')->json('POST', '/api/orders', $sellData);


        $buyData = ['trade_type' => 'buy', 'currency' => $this->currency, 'coin' => $this->coin, 'type' => 'limit', 'price' => 3114500, 'quantity' => 1];
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $buyData);

        $this->checkPrice(3114500);
    }
}
