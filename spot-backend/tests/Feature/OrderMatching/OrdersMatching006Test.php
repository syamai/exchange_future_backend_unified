<?php

namespace Tests\Feature\OrderMatching;

use App\Consts;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class OrdersMatching006Test extends OrdersMatchingTestBase
{
    protected $initData = [
        ['trade_type' => 'buy', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '20000', 'quantity' => '6'],
        ['trade_type' => 'sell', 'currency' => 'usd', 'coin' => 'btc', 'type' => 'limit', 'fee' => '0', 'price' => '20000', 'quantity' => '12'],
    ];

    protected $result = [
        ['id' => 1, 'status' => Consts::ORDER_STATUS_CANCELED],
        ['id' => 2, 'status' => Consts::ORDER_STATUS_PENDING],
    ];

    /**
     * @group orderMatching
     * @group orderMatching6
     * Test clear cache
     *
     * @return void
     */
    public function testOrderMatching()
    {
        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $this->initData[0]);

        $orderId = $this->getBaseId() + 1;
        $this->actingAs($this->user1, 'api')->json('PUT', "/api/orders/$orderId/cancel");

        $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $this->initData[1]);

        $this->checkResult();
    }
}
