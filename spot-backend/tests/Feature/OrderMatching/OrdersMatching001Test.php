<?php

namespace Tests\Feature\OrderMatching;

use App\Consts;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class OrdersMatching001Test extends OrdersMatchingTestBase
{
    protected $initData = [
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '200000', 'quantity' => '6'],
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '180000', 'quantity' => '5'],
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '195000', 'quantity' => '10'],
        ['trade_type' => 'sell', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '210000', 'quantity' => '13'],
        ['trade_type' => 'sell', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '200000', 'quantity' => '12'],
        ['trade_type' => 'sell', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '190000', 'quantity' => '5'],
    ];

    protected $result = [
        ['id' => 1, 'executed_quantity' => 6, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ['id' => 2, 'executed_quantity' => 0, 'status' => Consts::ORDER_STATUS_PENDING],
        ['id' => 3, 'executed_quantity' => 5, 'status' => Consts::ORDER_STATUS_EXECUTING],
        ['id' => 4, 'executed_quantity' => 0, 'status' => Consts::ORDER_STATUS_PENDING],
        ['id' => 5, 'executed_quantity' => 6, 'status' => Consts::ORDER_STATUS_EXECUTING],
        ['id' => 6, 'executed_quantity' => 5, 'status' => Consts::ORDER_STATUS_EXECUTED],
    ];

    /**
     * @group orderMatching
     * @group orderMatching1
     * A basic test example.
     *
     * @return void
     */
    public function testOrderMatching()
    {
        parent::testOrderMatching();
    }
}
