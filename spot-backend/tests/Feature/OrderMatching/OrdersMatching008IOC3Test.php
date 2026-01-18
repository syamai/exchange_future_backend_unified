<?php

namespace Tests\Feature\OrderMatching;

use App\Consts;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class OrdersMatching008IOC3Test extends OrdersMatchingTestBase
{
    protected $initData = [
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'market', 'fee' => '0', 'ioc' => 1, 'quantity' => '12'],
        ['trade_type' => 'sell', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'market', 'fee' => '0', 'ioc' => 1, 'quantity' => '12'],
    ];

    protected $result = [
        ['id' => 1, 'status' => Consts::ORDER_STATUS_CANCELED],
        ['id' => 2, 'status' => Consts::ORDER_STATUS_CANCELED],
    ];

    /**
     * @group orderMatching
     * @group orderMatchingIOC
     * @group orderMatching8IOC3
     * Test market order (with ioc)
     *
     * @return void
     */
    public function testOrderMatching()
    {
        parent::testOrderMatching();
    }
}
