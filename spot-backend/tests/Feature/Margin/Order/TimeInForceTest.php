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

class TimeInForceTest extends BaseOrderTest
{

    /**
     * Test limit orders
     * @group Margin
     * @group OrderTimeInForce
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
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '2',
                'price' => '10000',
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_IOC,
            ],[
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'remaining' => '1', 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 3, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group OrderTimeInForce
     *
     * @return void
     */
    public function testOrder1()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_IOC,
            ],[
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '2',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_CANCELED],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group OrderTimeInForce
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
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_IOC,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 3, 'remaining' => '0', 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group OrderTimeInForce
     *
     * @return void
     */
    public function testOrder3()
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
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_FOK,
            ],[
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
            ['id' => 2, 'remaining' => '2', 'status' => Consts::ORDER_STATUS_CANCELED],
            ['id' => 3, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group OrderTimeInForce
     *
     * @return void
     */
    public function testOrder4()
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
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_FOK,
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'remaining' => '2', 'status' => Consts::ORDER_STATUS_CANCELED],
            ['id' => 3, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group OrderTimeInForce
     *
     * @return void
     */
    public function testOrder5()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_FOK,
            ],[
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '2',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_CANCELED],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group OrderTimeInForce
     *
     * @return void
     */
    public function testOrder6()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],[
                'user_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],[
                'user_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '2',
                'price' => '10000',
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_FOK,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 3, 'remaining' => '0', 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }
}
