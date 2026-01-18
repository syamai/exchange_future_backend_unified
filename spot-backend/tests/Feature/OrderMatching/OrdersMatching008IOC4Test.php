<?php

namespace Tests\Feature\OrderMatching;

use App\Consts;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class OrdersMatching008IOC4Test extends OrdersMatchingTestBase
{
    protected $initData = [
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '20000', 'quantity' => '6'],
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '21000', 'quantity' => '6'],
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '21000', 'quantity' => '6'],
        ['trade_type' => 'sell', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'market', 'fee' => '0', 'ioc' => 1, 'quantity' => '13'],
    ];

    protected $result = [
        // ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
        // ['id' => 2, 'status' => Consts::ORDER_STATUS_EXECUTED],
        // ['id' => 3, 'status' => Consts::ORDER_STATUS_EXECUTED],
        // ['id' => 4, 'status' => Consts::ORDER_STATUS_EXECUTED],

        // ['id' => 5, 'status' => Consts::ORDER_STATUS_EXECUTED, 'quantity' => 6, 'original_id' => 4],
        // ['id' => 6, 'status' => Consts::ORDER_STATUS_REMOVED, 'quantity' => 7, 'original_id' => 4],
        // ['id' => 7, 'status' => Consts::ORDER_STATUS_EXECUTED, 'quantity' => 6, 'original_id' => 4],
        // ['id' => 8, 'status' => Consts::ORDER_STATUS_CANCELED, 'quantity' => 1, 'original_id' => 4],
    ];

    /**
     * @group orderMatching
     * @group orderMatchingIOC
     * @group orderMatching8IOC4
     * Test market order (with ioc)
     *
     * @return void
     */
    public function testOrderMatching()
    {
        parent::testOrderMatching();
    }
}
