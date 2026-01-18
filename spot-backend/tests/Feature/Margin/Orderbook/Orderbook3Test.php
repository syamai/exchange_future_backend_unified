<?php

namespace Tests\Feature\Margin\Orderbook;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use App\Consts;
use App\Utils;
use App\Models\MarginOrder;
use OrderService;
use MatchingEngine;

class Orderbook3Test extends BaseOrderbookTest
{

    /**
     * Test time in force
     * @group Margin
     * @group MarginOrderbook
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
            'buy' => [
                ['price' => '10000', 'quantity' => '1', 'count' => 1],
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test time in force
     * @group Margin
     * @group MarginOrderbook
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
            'buy' => [
                ['price' => '10000', 'quantity' => '2', 'count' => 1],
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test time in force
     * @group Margin
     * @group MarginOrderbook
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
            'buy' => [
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test time in force
     * @group Margin
     * @group MarginOrderbook
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
            'buy' => [
                ['price' => '10000', 'quantity' => '2', 'count' => 2],
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test time in force
     * @group Margin
     * @group MarginOrderbook
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
            'buy' => [
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test time in force
     * @group Margin
     * @group MarginOrderbook
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
            'buy' => [
                ['price' => '10000', 'quantity' => '2', 'count' => 1],
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test time in force
     * @group Margin
     * @group MarginOrderbook
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
            ],
        ];
        $outputs = [
            'buy' => [
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }
}
