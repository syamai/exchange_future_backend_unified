<?php

namespace Tests\Feature\OrderMatching;

use App\Consts;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class OrdersMatching003Test extends OrdersMatchingTestBase
{
    protected $initData = [
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '19000', 'quantity' => '30'],
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '20000', 'quantity' => '10'],
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '20000', 'quantity' => '10'],
        ['trade_type' => 'sell', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '20000', 'quantity' => '5'],
        ['trade_type' => 'sell', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '20000', 'quantity' => '5'],
    ];

    protected $result = [
        ['id' => 1, 'executed_quantity' => 0, 'status' => Consts::ORDER_STATUS_PENDING],
        ['id' => 2, 'executed_quantity' => 10, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ['id' => 3, 'executed_quantity' => 0, 'status' => Consts::ORDER_STATUS_PENDING],
        ['id' => 4, 'executed_quantity' => 5, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ['id' => 5, 'executed_quantity' => 5, 'status' => Consts::ORDER_STATUS_EXECUTED],
    ];

    /**
     * @group orderMatching
     * @group orderMatching3
     * A basic test example.
     *
     * @return void
     */
    public function testOrderMatching()
    {
        parent::testOrderMatching();
    }
}
