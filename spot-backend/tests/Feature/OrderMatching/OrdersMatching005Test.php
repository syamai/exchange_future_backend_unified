<?php

namespace Tests\Feature\OrderMatching;

use App\Consts;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class OrdersMatching005Test extends OrdersMatchingTestBase
{
    protected $initData = [
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '20000', 'quantity' => '6'],
        ['trade_type' => 'sell', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '20000', 'quantity' => '12'],
    ];

    protected $result = [
        ['id' => 1, 'executed_quantity' => 6, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ['id' => 2, 'executed_quantity' => 6, 'status' => Consts::ORDER_STATUS_EXECUTING],
    ];

    /**
     * @group orderMatching
     * @group orderMatching5
     * A basic test example.
     *
     * @return void
     */
    public function testOrderMatching()
    {
        parent::testOrderMatching();
    }
}
