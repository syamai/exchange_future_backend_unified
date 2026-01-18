<?php

namespace Tests\Feature\Margin\Order;

use Tests\Feature\Margin\BaseMarginTest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ExecutionService;
use OrderService;
use MatchingEngine;

class BaseOrderTest extends BaseMarginTest
{
    protected $symbol = 'BTCUSD';
    protected $userId = 1;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpAccount([
            'id' => $this->userId,
            'balance' => 1000000,
            'cross_balance' => 1000000,
            'cross_equity' => 1000000,
            'cross_margin' => 0,
            'order_margin' => 0,
            'available_balance' => 1000000,
            'max_available_balance' => 1000000,
        ]);
        $this->setUpInsuranceAccount();
        $this->setUpInstruments();
    }

    protected function clearData()
    {
        DB::table('margin_accounts')->truncate();
        DB::table('margin_balance_history')->truncate();
        DB::table('margin_orders')->truncate();
        DB::table('margin_trades')->truncate();
        DB::table('positions')->truncate();
        DB::table('margin_accounts')->truncate();
        DB::table('instruments')->truncate();
        DB::table('instrument_extra_informations')->truncate();
    }

    /**
     * Test order matching
     *
     * @return void
     */
    protected function doTest($inputs, $outputs)
    {
        $this->createOrders($inputs);
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);
        $this->checkOutput($outputs);
    }

    protected function createOrders($inputs)
    {
        foreach ($inputs as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
    }

    protected function checkOutput($outputs)
    {
        foreach ($outputs as $output) {
            $this->assertDatabaseHas('margin_orders', $output);
        }
    }

    protected function getOrderData($data)
    {
        $data = parent::getOrderData($data);
        if (!isset($data['account_id'])) {
            $data['account_id'] = $this->userId;
        }
        return $data;
    }
}
