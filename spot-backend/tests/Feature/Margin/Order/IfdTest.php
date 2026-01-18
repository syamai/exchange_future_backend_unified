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

class IfdTest extends BaseOrderTest
{

    /**
     * Test limit orders
     * @group Margin
     * @group IfdOrder
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
                'stop_type' => Consts::ORDER_STOP_TYPE_LIMIT,
                'stop_price' => '12000',
                'stop_condition' => Consts::ORDER_STOP_CONDITION_GE,
                'trigger' => Consts::ORDER_STOP_TRIGGER_LAST,
                'pair_type' => Consts::ORDER_PAIR_TYPE_IFD,
                'status' => Consts::ORDER_STATUS_STOPPING,
            ],[
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
                'pair_type' => Consts::ORDER_PAIR_TYPE_IFD,
                'reference_id' => 1,
            ],
        ];

        $this->createOrders($inputs);

        DB::table('instrument_extra_informations')
            ->where('symbol', $this->symbol)
            ->update(['last_price' => 12000]);

        Artisan::call('margin:trigger_stop_order');

        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_STOPPING],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->checkOutput($outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group IfdOrder
     *
     * @return void
     */
    public function testOrder1()
    {
        DB::table('margin_accounts')->insert([
            'id' => $this->userId + 1,
            'balance' => 10000,
            'cross_balance' => 10000,
            'cross_equity' => 10000,
            'cross_margin' => 0,
            'order_margin' => 0,
            'available_balance' => 10000,
            'max_available_balance' => 10000,
        ]);

        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
                'stop_type' => Consts::ORDER_STOP_TYPE_LIMIT,
                'stop_price' => '12000',
                'stop_condition' => Consts::ORDER_STOP_CONDITION_GE,
                'trigger' => Consts::ORDER_STOP_TRIGGER_LAST,
                'pair_type' => Consts::ORDER_PAIR_TYPE_IFD,
                'status' => Consts::ORDER_STATUS_STOPPING,
            ],[
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
                'pair_type' => Consts::ORDER_PAIR_TYPE_IFD,
                'reference_id' => 1,
            ],[
                'account_id' => 2,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1',
                'price' => '10000',
                'pair_type' => Consts::ORDER_PAIR_TYPE_IFD,
                'reference_id' => 1,
            ],
        ];

        $this->createOrders($inputs);

        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);
        DB::table('instrument_extra_informations')
            ->where('symbol', $this->symbol)
            ->update(['last_price' => 12000]);

        Artisan::call('margin:trigger_stop_order');

        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        $this->checkOutput($outputs);
    }
}
