<?php

namespace Tests\Feature\OrderMatching;

use App\Consts;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class OrdersMatching007Test extends OrdersMatchingTestBase
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
     * @group orderMatching7
     * Test cancel order
     *
     * @return void
     */
    public function testOrderMatching()
    {
        $response = $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $this->initData[0]);
        $data = $response->json();
        if (isset($data['data']['id'])) {
            $job = new \App\Jobs\ProcessOrderRequest($data['data']['id'], \App\Jobs\ProcessOrderRequest::CREATE);
            $job->handle();
        }

        $this->clearCache();

        $response2 = $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $this->initData[1]);
        $data2 = $response2->json();
        if (isset($data2['data']['id'])) {
            $job2 = new \App\Jobs\ProcessOrderRequest($data2['data']['id'], \App\Jobs\ProcessOrderRequest::CREATE);
            $job2->handle();
        }

        $this->checkResult();
    }
}
