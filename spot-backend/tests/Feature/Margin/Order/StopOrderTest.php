<?php

namespace Tests\Feature\Margin\Order;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use App\Consts;
use App\Utils;
use Carbon\Carbon;
use IndexService;
use InstrumentService;
use OrderService;
use MatchingEngine;

class StopOrderTest extends BaseOrderTest
{

    /**
     * Test stop orders
     * @group Margin
     * @group StopOrder
     *
     * @return void
     */
    public function testOrder0()
    {
        InstrumentService::update($this->symbol, ['last_price' => 11500]);

        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'stop_type' => Consts::ORDER_STOP_TYPE_LIMIT,
                'price' => '11240',
                'trigger' => 'last',
                'stop_condition' => Consts::ORDER_STOP_CONDITION_GE,
                'stop_price' => '12000',
                'quantity' => '1',
                'status' => Consts::ORDER_STATUS_STOPPING,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_STOPPING],
        ];
        $this->doTest($inputs, $outputs);

        Artisan::call('margin:trigger_stop_order');

        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_STOPPING],
        ];
        $this->checkOutput($outputs);
    }

    /**
     * Test stop orders
     * @group Margin
     * @group StopOrder
     *
     * @return void
     */
    public function testOrder1()
    {
        InstrumentService::update($this->symbol, ['last_price' => 11500]);

        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'stop_type' => Consts::ORDER_STOP_TYPE_LIMIT,
                'price' => '11240',
                'trigger' => 'last',
                'stop_condition' => Consts::ORDER_STOP_CONDITION_GE,
                'stop_price' => '12000',
                'quantity' => '1',
                'status' => Consts::ORDER_STATUS_STOPPING,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_STOPPING],
        ];
        $this->doTest($inputs, $outputs);

        InstrumentService::update($this->symbol, ['last_price' => 12000]);

        Artisan::call('margin:trigger_stop_order');

        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->checkOutput($outputs);
    }

    /**
     * Test stop orders
     * @group Margin
     * @group StopOrder
     *
     * @return void
     */
    public function testOrder2()
    {
        InstrumentService::update($this->symbol, ['last_price' => 12500]);

        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'stop_type' => Consts::ORDER_STOP_TYPE_LIMIT,
                'price' => '11240',
                'trigger' => 'last',
                'stop_condition' => Consts::ORDER_STOP_CONDITION_LE,
                'stop_price' => '12000',
                'quantity' => '1',
                'status' => Consts::ORDER_STATUS_STOPPING,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_STOPPING],
        ];
        $this->doTest($inputs, $outputs);

        InstrumentService::update($this->symbol, ['last_price' => 11500]);

        Artisan::call('margin:trigger_stop_order');

        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->checkOutput($outputs);
    }

    /**
     * Test stop orders
     * @group Margin
     * @group StopOrder
     *
     * @return void
     */
    public function testOrder3()
    {
        InstrumentService::update($this->symbol, ['mark_price' => 11500]);

        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'stop_type' => Consts::ORDER_STOP_TYPE_LIMIT,
                'price' => '11240',
                'trigger' => 'mark',
                'stop_condition' => Consts::ORDER_STOP_CONDITION_GE,
                'stop_price' => '12000',
                'quantity' => '1',
                'status' => Consts::ORDER_STATUS_STOPPING,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_STOPPING],
        ];
        $this->doTest($inputs, $outputs);

        InstrumentService::update($this->symbol, ['mark_price' => 12000]);

        Artisan::call('margin:trigger_stop_order');

        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->checkOutput($outputs);
    }

    /**
     * Test stop orders
     * @group Margin
     * @group StopOrder
     *
     * @return void
     */
    public function testOrder4()
    {
        InstrumentService::update($this->symbol, ['last_price' => 12500]);
        $instrument = InstrumentService::get($this->symbol);
        IndexService::insert($instrument->reference_index, 12500, Carbon::now());

        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'stop_type' => Consts::ORDER_STOP_TYPE_LIMIT,
                'price' => '11240',
                'trigger' => 'index',
                'stop_condition' => Consts::ORDER_STOP_CONDITION_LE,
                'stop_price' => '12000',
                'quantity' => '1',
                'status' => Consts::ORDER_STATUS_STOPPING,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_STOPPING],
        ];
        $this->doTest($inputs, $outputs);

        IndexService::insert($instrument->reference_index, 11500, Carbon::now()->addSeconds(1));

        Artisan::call('margin:trigger_stop_order');

        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->checkOutput($outputs);
    }

    /**
     * Test stop orders
     * @group Margin
     * @group StopOrder
     *
     * @return void
     */
    public function testOrder5()
    {
        InstrumentService::update($this->symbol, ['last_price' => 11500]);

        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'stop_type' => Consts::ORDER_STOP_TYPE_TRAILING_STOP,
                'price' => '11240',
                'trigger' => 'last',
                'stop_condition' => Consts::ORDER_STOP_CONDITION_GE,
                'vertex_price' => '11500',
                'trail_value' => '1000',
                'stop_price' => '12500',
                'quantity' => '1',
                'status' => Consts::ORDER_STATUS_STOPPING,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_STOPPING],
        ];
        $this->doTest($inputs, $outputs);

        InstrumentService::update($this->symbol, ['last_price' => 12000]);

        Artisan::call('margin:trigger_stop_order');

        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_STOPPING],
        ];
        $this->checkOutput($outputs);
    }

    /**
     * Test stop orders
     * @group Margin
     * @group StopOrder
     *
     * @return void
     */
    public function testOrder6()
    {
        InstrumentService::update($this->symbol, ['last_price' => 11500]);

        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'stop_type' => Consts::ORDER_STOP_TYPE_TRAILING_STOP,
                'price' => '11240',
                'trigger' => 'last',
                'stop_condition' => Consts::ORDER_STOP_CONDITION_GE,
                'vertex_price' => '11500',
                'trail_value' => '1000',
                'stop_price' => '12500',
                'quantity' => '1',
                'status' => Consts::ORDER_STATUS_STOPPING,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_STOPPING],
        ];
        $this->doTest($inputs, $outputs);

        InstrumentService::update($this->symbol, ['last_price' => 12500]);

        Artisan::call('margin:trigger_stop_order');

        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->checkOutput($outputs);
    }

    /**
     * Test stop orders
     * @group Margin
     * @group StopOrder
     *
     * @return void
     */
    public function testOrder7()
    {
        InstrumentService::update($this->symbol, ['last_price' => 11500]);

        $inputs = [
            [
                'account_id' => 1,
                'side' => 'buy',
                'type' => 'limit',
                'stop_type' => Consts::ORDER_STOP_TYPE_TRAILING_STOP,
                'price' => '11240',
                'trigger' => 'last',
                'stop_condition' => Consts::ORDER_STOP_CONDITION_GE,
                'vertex_price' => '11500',
                'trail_value' => '1000',
                'stop_price' => '12500',
                'quantity' => '1',
                'status' => Consts::ORDER_STATUS_STOPPING,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_STOPPING],
        ];
        $this->doTest($inputs, $outputs);

        InstrumentService::update($this->symbol, ['last_price' => 11000]);
        Artisan::call('margin:trigger_stop_order');
        $outputs = [
            ['id' => 1, 'vertex_price' => '11000'],
        ];
        $this->checkOutput($outputs);

        InstrumentService::update($this->symbol, ['last_price' => 12000]);
        Artisan::call('margin:trigger_stop_order');
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->checkOutput($outputs);
    }

    /**
     * Test stop orders
     * @group Margin
     * @group StopOrder
     *
     * @return void
     */
    public function testOrder8()
    {
        InstrumentService::update($this->symbol, ['last_price' => 11500]);

        $inputs = [
            [
                'account_id' => 1,
                'side' => 'sell',
                'type' => 'limit',
                'stop_type' => Consts::ORDER_STOP_TYPE_TRAILING_STOP,
                'price' => '11240',
                'trigger' => 'last',
                'stop_condition' => Consts::ORDER_STOP_CONDITION_LE,
                'vertex_price' => '11500',
                'trail_value' => '-1000',
                'stop_price' => '10500',
                'quantity' => '1',
                'status' => Consts::ORDER_STATUS_STOPPING,
            ],
        ];
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_STOPPING],
        ];
        $this->doTest($inputs, $outputs);

        InstrumentService::update($this->symbol, ['last_price' => 12000]);
        Artisan::call('margin:trigger_stop_order');
        $outputs = [
            ['id' => 1, 'vertex_price' => '12000'],
        ];
        $this->checkOutput($outputs);

        InstrumentService::update($this->symbol, ['last_price' => 11000]);
        Artisan::call('margin:trigger_stop_order');
        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        $this->checkOutput($outputs);
    }
}
