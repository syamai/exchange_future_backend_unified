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

class ReduceOnlyTest extends BaseOrderTest
{

    /**
     * Test limit orders
     * @group Margin
     * @group ReduceOnlyOrder
     *
     * @return void
     */
    public function testOrder0()
    {
        DB::table('margin_accounts')->insert([
            'id' => $this->userId + 1,
            'balance' => 10000,
            'order_margin' => 0,
            'available_balance' => 10000,
            'max_available_balance' => 10000,
        ]);

        $inputs = [
            ['account_id' => 1, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 2, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $this->doTest($inputs, []);

        $inputs = [

            ['account_id' => 1, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000', 'is_reduce_only' => 1],

            ['account_id' => 2, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 1, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 3, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 4, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group ReduceOnlyOrder
     *
     * @return void
     */
    public function testOrder1()
    {
        DB::table('margin_accounts')->insert([
            'id' => $this->userId + 1,
            'balance' => 10000,
            'order_margin' => 0,
            'available_balance' => 10000,
            'max_available_balance' => 10000,
        ]);

        $inputs = [
            ['account_id' => 1, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 2, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $this->doTest($inputs, []);

        $inputs = [

            ['account_id' => 1, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '13000', 'is_reduce_only' => 1],

            ['account_id' => 1, 'side' => 'sell', 'type' => 'limit', 'quantity' => '200', 'price' => '12000'],
            ['account_id' => 2, 'side' => 'buy', 'type' => 'limit', 'quantity' => '200', 'price' => '12000'],

            ['account_id' => 2, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '13000'],
        ];
        $outputs = [
            ['id' => 1, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 2, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 3, 'remaining' => 100, 'status' => Consts::ORDER_STATUS_CANCELED],
            ['id' => 4, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 5, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
            ['id' => 6, 'remaining' => 100, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->doTest($inputs, $outputs);
    }
}
