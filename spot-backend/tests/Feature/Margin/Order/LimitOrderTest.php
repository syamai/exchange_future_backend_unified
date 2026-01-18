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

class LimitOrderTest extends BaseOrderTest
{

    /**
     * Test limit orders
     * @group Margin
     * @group MarginLimit
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
                'quantity' => '1',
                'price' => '12000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginLimit
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
                'quantity' => '2',
                'price' => '10000',
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'remaining' => 1, 'status' => Consts::ORDER_STATUS_EXECUTING],
            ['id' => 2, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginLimit
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
                'quantity' => '1',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginLimit
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
            ],
        ];
        $outputs = [
            ['id' => 1, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'remaining' => 1, 'status' => Consts::ORDER_STATUS_EXECUTING],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginLimit
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
                'quantity' => '3',
                'price' => '10000',
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'remaining' => 1, 'status' => Consts::ORDER_STATUS_EXECUTING],
            ['id' => 2, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 3, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginLimit
     *
     * @return void
     */
    public function testOrder5()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '2',
                'price' => '10000',
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
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
            ['id' => 2, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 3, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginLimit
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
                'price' => '11000',
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 3, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginLimit
     *
     * @return void
     */
    public function testOrder7()
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
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '9000',
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
            ['id' => 2, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 3, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginLimit
     *
     * @return void
     */
    public function testOrder8()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '2',
                'price' => '10000',
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'remaining' => 1, 'status' => Consts::ORDER_STATUS_EXECUTING],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);

        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 3, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }



    /**
     * Test limit orders
     * @group Margin
     * @group MarginLimit9
     *
     * @return void
     */
    // public function testOrder9()
    // {
    //     $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 5]);
    //     $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 5]);
    //     $this->setUpAccount(['id' => 7, 'owner_id' => 7, 'balance' => 5]);
    //     $inputs = [
    //         ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
    //         ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
    //     ];
    //     $outputs = [
    //         ['id' => 1, 'status' => Consts::ORDER_STATUS_EXECUTED],
    //         ['id' => 2, 'status' => Consts::ORDER_STATUS_EXECUTED],
    //     ];
    //     $this->doTest($inputs, $outputs);

    //     $inputs = [
    //         ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '1'],
    //         ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '1'],
    //     ];
    //     $outputs = [
    //         ['id' => 3, 'status' => Consts::ORDER_STATUS_CANCELED],
    //         ['id' => 4, 'status' => Consts::ORDER_STATUS_PENDING],
    //     ];
    //     $this->doTest($inputs, $outputs);

    //     $inputs = [
    //         ['account_id' => 7, 'side' => 'sell', 'type' => 'limit', 'quantity' => '1', 'price' => '1'],
    //     ];
    //     $outputs = [
    //         ['id' => 4, 'status' => Consts::ORDER_STATUS_EXECUTING],
    //         ['id' => 5, 'status' => Consts::ORDER_STATUS_EXECUTED],
    //     ];
    //     $this->doTest($inputs, $outputs);
    // }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginLimit10
     *
     * @return void
     */
    // public function testOrder10()
    // {
    //     $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 100]);
    //     $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 5]);
    //     $this->setUpAccount(['id' => 7, 'owner_id' => 7, 'balance' => 5]);
    //     $inputs = [
    //         ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
    //         ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
    //     ];
    //     $outputs = [
    //         ['id' => 1, 'status' => Consts::ORDER_STATUS_EXECUTED],
    //         ['id' => 2, 'status' => Consts::ORDER_STATUS_EXECUTED],
    //     ];
    //     $this->doTest($inputs, $outputs);

    //     $inputs = [
    //         ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '1'],
    //         ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '1'],
    //     ];
    //     $outputs = [
    //         ['id' => 3, 'status' => Consts::ORDER_STATUS_PENDING],
    //         ['id' => 4, 'status' => Consts::ORDER_STATUS_CANCELED],
    //     ];
    //     $this->doTest($inputs, $outputs);

    //     $inputs = [
    //         ['account_id' => 7, 'side' => 'sell', 'type' => 'limit', 'quantity' => '1', 'price' => '1'],
    //     ];
    //     $outputs = [
    //         ['id' => 3, 'status' => Consts::ORDER_STATUS_EXECUTING],
    //         ['id' => 5, 'status' => Consts::ORDER_STATUS_EXECUTED],
    //     ];
    //     $this->doTest($inputs, $outputs);
    // }
}
