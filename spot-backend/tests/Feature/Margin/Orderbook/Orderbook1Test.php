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

class OrderbookTest extends BaseOrderbookTest
{

    /**
     * Test limit orders
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
                'quantity' => '1',
                'price' => '12000',
            ],
        ];
        $outputs = [
            'buy' => [
                ['price' => '10000', 'quantity' => '1', 'count' => 1],
            ],
            'sell' => [
                ['price' => '12000', 'quantity' => '1', 'count' => 1],
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
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
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],[
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '12000',
            ]
        ];
        $outputs = [
            'buy' => [
                ['price' => '12000', 'quantity' => '1', 'count' => 1],
                ['price' => '10000', 'quantity' => '1', 'count' => 1],
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
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
                'price' => '12000',
            ],[
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ]
        ];
        $outputs = [
            'buy' => [
                ['price' => '12000', 'quantity' => '1', 'count' => 1],
                ['price' => '10000', 'quantity' => '1', 'count' => 1],
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
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
                'price' => '12000',
            ],[
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
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '11000',
            ]
        ];
        $outputs = [
            'buy' => [
                ['price' => '12000', 'quantity' => '1', 'count' => 1],
                ['price' => '11000', 'quantity' => '2', 'count' => 2],
                ['price' => '10000', 'quantity' => '1', 'count' => 1],
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
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
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '12000',
            ]
        ];
        $outputs = [
            'buy' => [
            ],
            'sell' => [
                ['price' => '10000', 'quantity' => '1', 'count' => 1],
                ['price' => '12000', 'quantity' => '1', 'count' => 1],
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
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
                'price' => '12000',
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ]
        ];
        $outputs = [
            'buy' => [
            ],
            'sell' => [
                ['price' => '10000', 'quantity' => '1', 'count' => 1],
                ['price' => '12000', 'quantity' => '1', 'count' => 1],
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
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
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '12000',
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
                'price' => '11000',
            ],[
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '11000',
            ]
        ];
        $outputs = [
            'buy' => [
            ],
            'sell' => [
                ['price' => '10000', 'quantity' => '1', 'count' => 1],
                ['price' => '11000', 'quantity' => '2', 'count' => 2],
                ['price' => '12000', 'quantity' => '1', 'count' => 1],
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test market orders
     * @group Margin
     * @group MarginOrderbook
     *
     * @return void
     */
    public function testOrder8()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'market',
                'quantity' => '1',
            ]
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
     * Test limit orders
     * @group Margin
     * @group MarginOrderbook
     *
     * @return void
     */
    public function testOrder9()
    {
        $inputs = [
            [
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
            'buy' => [
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginOrderbook
     *
     * @return void
     */
    public function testOrder10()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '3',
                'price' => '10000',
            ],
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
            [
                'account_id' => 1,
                'side' => 'sell',
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
     * Test limit orders
     * @group Margin
     * @group MarginOrderbook
     *
     * @return void
     */
    public function testOrder11()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '3',
                'price' => '10000',
            ],
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '3',
                'price' => '10000',
            ],
        ];
        $outputs = [
            'buy' => [
            ],
            'sell' => [
                ['price' => '10000', 'quantity' => '1', 'count' => 1],
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginOrderbook
     *
     * @return void
     */
    public function testOrder12()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '3',
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
     * Test limit orders
     * @group Margin
     * @group MarginOrderbook
     *
     * @return void
     */
    public function testOrder13()
    {
        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
            ],
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '9000',
            ],
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '3',
                'price' => '11000',
            ],
        ];
        $outputs = [
            'buy' => [
                ['price' => '11000', 'quantity' => '1', 'count' => 1],
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }
}
