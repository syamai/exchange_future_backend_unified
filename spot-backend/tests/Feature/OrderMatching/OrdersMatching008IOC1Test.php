<?php

namespace Tests\Feature\OrderMatching;

use App\Consts;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class OrdersMatching008IOC1Test extends OrdersMatchingTestBase
{
    protected $initData = [
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '20000', 'quantity' => '6'],
        ['trade_type' => 'sell', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'market', 'fee' => '0', 'ioc' => 1, 'quantity' => '12'],
    ];

    protected $result = [
        ['id' => 1, 'executed_quantity' => 6, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ['id' => 2, 'executed_quantity' => 6, 'status' => Consts::ORDER_STATUS_EXECUTED],
    ];

    /**
     * @group orderMatching
     * @group orderMatchingIOC
     * @group orderMatching8IOC1
     * Test market order (with ioc)
     *
     * @return void
     */
    public function testOrderMatching()
    {
        parent::testOrderMatching();
    }
}
