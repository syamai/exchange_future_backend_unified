<?php

namespace Tests\Feature\OrderMatching;

use App\Models\Order;
use App\Jobs\ProcessOrder;
use App\Jobs\ProcessOrderRequest;
use Tests\Feature\BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use PassportHmac\Http\Middleware\HmacTokenMiddleware;

class OrdersMatchingTestBase extends BaseTestCase
{

    protected $initData = [];

    protected $result = [];

    public function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Disable HMAC middleware for testing
        $this->withoutMiddleware(HmacTokenMiddleware::class);
    }

    protected function getBaseId()
    {
        return Order::min('id') - 1;
    }

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testOrderMatching()
    {
        // Use Passport::actingAs for API authentication
        Passport::actingAs($this->user1, ['*']);

        foreach ($this->initData as $input) {
            $response = $this->json('POST', '/api/orders', $input);
            // Debug: dump response if not successful
            if ($response->status() !== 200 && $response->status() !== 201) {
                dump('Order creation failed:', $response->status(), $response->json());
            } else {
                // Execute ProcessOrderRequest synchronously to change status from NEW to PENDING
                // This step is normally done by queue worker, but in tests we need to do it manually
                $data = $response->json();
                if (isset($data['data']['id'])) {
                    $job = new ProcessOrderRequest($data['data']['id'], ProcessOrderRequest::CREATE);
                    $job->handle();
                }
            }
        }
        $this->checkResult();
    }

    protected function checkResult()
    {
        // Execute ProcessOrder job synchronously instead of dispatching to queue
        // Artisan::call dispatches to async queue, which doesn't execute in test context
        $job = new ProcessOrder($this->initData[0]['currency'], $this->initData[0]['coin']);
        $job->handle();

        $baseId = $this->getBaseId();
        foreach ($this->result as $order) {
            $order['id'] = $order['id'] + $baseId;
            $this->assertDatabaseHas('orders', $order);
        }
        $this->assertTrue(true);
    }
}
