<?php

namespace Tests\Feature\Margin\Order;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use App\Consts;
use App\Utils;
use OrderService;
use MatchingEngine;

class MarketOrderTest extends BaseOrderTest
{

    /**
     * Test limit orders
     * @group Margin
     * @group MarginMarket
     *
     * @return void
     */
    public function testOrder1()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'market',
                'quantity' => '2',
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_IOC,
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'remaining' => 2, 'status' => Consts::ORDER_STATUS_CANCELED],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginMarket
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
                'type' => 'market',
                'quantity' => '1',
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_IOC,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginMarket
     *
     * @return void
     */
    public function testOrder3()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'market',
                'quantity' => '1',
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_IOC,
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'market',
                'quantity' => '2',
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_IOC,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_CANCELED],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_CANCELED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginMarket
     *
     * @return void
     */
    public function testOrder4()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],[
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'market',
                'quantity' => '2',
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_IOC,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'remaining' => 1, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginMarket
     *
     * @return void
     */
    public function testOrder5()
    {
        DB::table('margin_accounts')->insert([
            'id' => $this->userId + 1,
            'balance' => 0,
            'order_margin' => 0,
            'available_balance' => 0,
        ]);
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1000',
                'price' => '10000',
            ],[
                'account_id' => 2,
                'side' => 'buy',
                'type' => 'market',
                'quantity' => '1000',
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_IOC,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
            ['id' => 2, 'remaining' => 1000, 'status' => Consts::ORDER_STATUS_CANCELED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginMarket
     *
     * @return void
     */
    public function testOrder6()
    {
        DB::table('margin_accounts')->insert([
            'id' => $this->userId + 1,
            'balance' => 0,
            'order_margin' => 0,
            'available_balance' => 0,
        ]);
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1000',
                'price' => '10000',
            ],[
                'account_id' => 2,
                'side' => 'buy',
                'type' => 'market',
                'quantity' => '1000',
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_IOC,
            ],[
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1000',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'remaining' => 1000, 'status' => Consts::ORDER_STATUS_CANCELED],
            ['id' => 1, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test market orders
     * @group Margin
     * @group MarginMarket
     *
     * @return void
     */
    public function testOrder7()
    {
        DB::table('margin_accounts')->insert([
            'id' => $this->userId + 1,
            'balance' => 0.001,
            'order_margin' => 0,
            'available_balance' => 0.001,
            'max_available_balance' => 0.001,
        ]);
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '10000',
                'price' => '10000',
            ],[
                'account_id' => 2,
                'side' => 'buy',
                'type' => 'market',
                'quantity' => '10000',
                'time_in_force' => Consts::ORDER_TIME_IN_FORCE_IOC,
            ],
        ];
        $outputs = [
            ['id' => 1, 'remaining' => 9132, 'status' => Consts::ORDER_STATUS_EXECUTING],
            ['id' => 2, 'remaining' => 9132, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }
}
