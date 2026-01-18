<?php

namespace Tests\Feature\OrderMatching;

use App\Models\Order;
use Tests\Feature\BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class OrdersMatchingTestBase extends BaseTestCase
{

    protected $initData = [];

    protected $result = [];

    public function setUp(): void
    {
        parent::setUp();
        Cache::flush();
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
        foreach ($this->initData as $input) {
            $this->actingAs($this->user1, 'api')->json('POST', '/api/orders', $input);
        }
        $this->checkResult();
    }

    protected function checkResult()
    {
        Artisan::call('order:process', [
            'currency' => $this->initData[0]['currency'],
            'coin' => $this->initData[0]['coin'],
        ]);
        $baseId = $this->getBaseId();
        foreach ($this->result as $order) {
            $order['id'] = $order['id'] + $baseId;
            $this->assertDatabaseHas('orders', $order);
        }
        $this->assertTrue(true);
    }
}
