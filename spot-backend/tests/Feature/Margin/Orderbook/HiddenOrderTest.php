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

class HiddendOrderTest extends BaseOrderbookTest
{

    /**
     * Test hidden order
     * @group Margin
     * @group MarginOrderbook
     * @group HiddenOrder
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
                'is_hidden' => 1,
                'display_quantity' => '0'
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
     * Test hidden order
     * @group Margin
     * @group MarginOrderbook
     * @group HiddenOrder
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
                'is_hidden' => 1,
                'display_quantity' => '0'
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
            ],
            'sell' => [
            ],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test hidden order
     * @group Margin
     * @group MarginOrderbook
     * @group HiddenOrder
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
                'quantity' => '2',
                'price' => '10000',
                'is_hidden' => 1,
                'display_quantity' => '1'
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
     * Test hidden order
     * @group Margin
     * @group MarginOrderbook
     * @group HiddenOrder
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
                'quantity' => '3',
                'price' => '10000',
                'is_hidden' => 1,
                'display_quantity' => '1'
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
}
