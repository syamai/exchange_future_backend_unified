<?php

namespace Tests\Feature\Margin\Order;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use App\Consts;
use App\Utils;
use App\Models\MarginOrder;
use OrderService;
use MatchingEngine;

class PostOnlyTest extends BaseOrderTest
{

    /**
     * Test limit orders
     * @group Margin
     * @group OrderPostOnly
     *
     * @return void
     */
    public function testOrder0()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
                'is_post_only' => 1,
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '2',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'remaining' => '1', 'status' => Consts::ORDER_STATUS_EXECUTING],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group OrderPostOnly
     *
     * @return void
     */
    public function testOrder1()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '2',
                'price' => '10000',
                'is_post_only' => 1,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_CANCELED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group OrderPostOnly
     *
     * @return void
     */
    public function testOrder2()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '2',
                'price' => '10000',
                'is_post_only' => 1,
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '2',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_CANCELED],
            ['id' => 3, 'status' => Consts::ORDER_STATUS_EXECUTING],
        ];
        $this->doTest($inputs, $outputs);
    }
}
