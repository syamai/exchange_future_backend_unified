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
        $response = $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $this->initData[0]);
        $data = $response->json();
        if (isset($data['data']['id'])) {
            $job = new \App\Jobs\ProcessOrderRequest($data['data']['id'], \App\Jobs\ProcessOrderRequest::CREATE);
            $job->handle();
        }

        $orderId = $this->getBaseId() + 1;
        $cancelResponse = $this->actingAs($this->user1, 'api')->json('PUT', "/api/orders/$orderId/cancel");
        // Execute cancel job if needed
        $cancelJob = new \App\Jobs\ProcessOrderRequest($orderId, \App\Jobs\ProcessOrderRequest::CANCEL);
        $cancelJob->handle();

        $response2 = $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $this->initData[1]);
        $data2 = $response2->json();
        if (isset($data2['data']['id'])) {
            $job2 = new \App\Jobs\ProcessOrderRequest($data2['data']['id'], \App\Jobs\ProcessOrderRequest::CREATE);
            $job2->handle();
        }

        $this->checkResult();
    }
}
