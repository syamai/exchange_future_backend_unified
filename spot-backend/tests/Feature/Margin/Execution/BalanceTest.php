<?php

namespace Tests\Feature\Margin\Execution;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use App\Consts;
use App\Utils;
use App\Models\MarginOrder;
use OrderService;
use MatchingEngine;
use ExecutionService;

class BalanceTest extends BaseExecutionTest
{

    /**
     * Test balance
     * @group Margin
     * @group MarginBalance
     * @group MarginBalance0
     *
     * @return void
     */
    public function testOrder0()
    {
        $this->updateMarkPrice(6000);
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.05, 'cross_margin' => 0, 'isolated_balance' => 0, 'order_margin' => 0.000095895833333, 'available_balance' => 0.049904104166284],
        ];
        $this->doTest1($inputs, $outputs);
    }

    /**
     * Test balance
     * @group Margin
     * @group MarginBalance
     * @group MarginBalance1
     *
     * @return void
     */
    public function testOrder1()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);

        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.05, 'cross_margin' => 0, 'isolated_balance' => 0, 'order_margin' => 0.000095895833333, 'available_balance' => 0.049904104166284],
        ];
        $order = OrderService::create($this->getOrderData(['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000']));
        ExecutionService::activeOrder($order);
        $this->assertValue($outputs);

        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.05, 'cross_margin' => 0, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.05],
        ];
        OrderService::cancel($order);
        $this->assertValue($outputs);
    }

    /**
     * Test balance
     * @group Margin
     * @group MarginBalance
     * @group MarginBalance2
     *
     * @return void
     */
    public function testOrder2()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.050002083333333, 'cross_margin' => 0.000089645833333, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.049912437499617],
            ['id' => 6, 'owner_id' => 6, 'balance' => 0.049993750000000, 'cross_margin' => 0.000089645833333, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.049904104166284],
        ];
        $this->doTest1($inputs, $outputs);
    }

    /**
     * Test balance
     * @group Margin
     * @group MarginBalance
     * @group MarginBalance3
     *
     * @return void
     */
    public function testOrder3()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.050002083333333, 'cross_margin' => 0.000089645833333, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.049912437499617],
            ['id' => 6, 'owner_id' => 6, 'balance' => 0.049993750000000, 'cross_margin' => 0.000089645833333, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.049904104166284],
        ];
        $this->doTest1($inputs, $outputs);

        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12500'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '50', 'price' => '12500'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.050169749999983, 'cross_margin' => 0.000044822916667, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.050124927082933],
            ['id' => 6, 'owner_id' => 6, 'balance' => 0.049824083333350, 'cross_margin' => 0.000044822916667, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.049779260416300],
        ];
        $this->doTest1($inputs, $outputs);
    }

    /**
     * Test balance
     * @group Margin
     * @group MarginBalance
     * @group MarginBalance4
     *
     * @return void
     */
    public function testOrder4()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.050002083333333, 'cross_margin' => 0.000089645833333, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.049912437499617],
            ['id' => 6, 'owner_id' => 6, 'balance' => 0.049993750000000, 'cross_margin' => 0.000089645833333, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.049904104166284],
        ];
        $this->doTest1($inputs, $outputs);

        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12500'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '50', 'price' => '12500'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.050169749999983, 'cross_margin' => 0.000044822916667, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.050124927082933],
            ['id' => 6, 'owner_id' => 6, 'balance' => 0.049824083333350, 'cross_margin' => 0.000044822916667, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.049779260416300],
        ];
        $this->doTest1($inputs, $outputs);


        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '25', 'price' => '11500'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '25', 'price' => '11500'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.050170293478244, 'cross_margin' => 0.000068208786232, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.050102084691128],
            ['id' => 6, 'owner_id' => 6, 'balance' => 0.049822452898567, 'cross_margin' => 0.000068208786232, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.049663664401301],
        ];
        $this->doTest1($inputs, $outputs);
    }

    /**
     * Test balance
     * @group Margin
     * @group MarginBalance
     * @group MarginBalance5
     *
     * @return void
     */
    public function testOrder5()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000', 'leverage' => 10],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.05, 'cross_margin' => 0, 'isolated_balance' => 0, 'order_margin' => 0.000846458333330, 'available_balance' => 0.049153541663288],
        ];
        $this->doTest1($inputs, $outputs);
    }

    /**
     * Test balance
     * @group Margin
     * @group MarginBalance
     * @group MarginBalance6
     *
     * @return void
     */
    public function testOrder6()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);

        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.05, 'cross_margin' => 0, 'isolated_balance' => 0, 'order_margin' => 0.000095895833333, 'available_balance' => 0.049904104166284],
        ];
        $order = OrderService::create($this->getOrderData(['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000', 'leverage' => 10]));
        ExecutionService::activeOrder($order);
        $this->assertValue($outputs);

        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.05, 'cross_margin' => 0, 'isolated_balance' => 0, 'order_margin' => 0, 'available_balance' => 0.05],
        ];
        OrderService::cancel($order);
        $this->assertValue($outputs);
    }

    /**
     * Test balance
     * @group Margin
     * @group MarginBalance
     * @group MarginBalance7
     *
     * @return void
     */
    public function testOrder7()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000', 'leverage' => 10],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000', 'leverage' => 10],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.050002083333333, 'cross_margin' => 0, 'isolated_balance' => 0.000840208333330, 'order_margin' => 0, 'available_balance' => 0.049161874996621],
            ['id' => 6, 'owner_id' => 6, 'balance' => 0.049993750000000, 'cross_margin' => 0, 'isolated_balance' => 0.000840208333330, 'order_margin' => 0, 'available_balance' => 0.049153541663288],
        ];
        $this->doTest1($inputs, $outputs);
    }

    /**
     * Test balance
     * @group Margin
     * @group MarginBalance
     * @group MarginBalance8
     *
     * @return void
     */
    public function testOrder8()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000', 'leverage' => 10],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000', 'leverage' => 10],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.050002083333333, 'cross_margin' => 0, 'isolated_balance' => 0.000840208333330, 'order_margin' => 0, 'available_balance' => 0.049161874996621],
            ['id' => 6, 'owner_id' => 6, 'balance' => 0.049993750000000, 'cross_margin' => 0, 'isolated_balance' => 0.000840208333330, 'order_margin' => 0, 'available_balance' => 0.049153541663288],
        ];
        $this->doTest1($inputs, $outputs);

        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12500', 'leverage' => 10],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '50', 'price' => '12500', 'leverage' => 10],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.050169749999983, 'cross_margin' => 0, 'isolated_balance' => 0.000420104166665, 'order_margin' => 0, 'available_balance' => 0.049749645829936],
            ['id' => 6, 'owner_id' => 6, 'balance' => 0.049824083333350, 'cross_margin' => 0, 'isolated_balance' => 0.000420104166665, 'order_margin' => 0, 'available_balance' => 0.049403979163303],
        ];
        $this->doTest1($inputs, $outputs);
    }

    /**
     * Test balance
     * @group Margin
     * @group MarginBalance
     * @group MarginBalance9
     *
     * @return void
     */
    public function testOrder9()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000', 'leverage' => 10],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000', 'leverage' => 10],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.050002083333333, 'cross_margin' => 0, 'isolated_balance' => 0.000840208333330, 'order_margin' => 0, 'available_balance' => 0.049161874996621],
            ['id' => 6, 'owner_id' => 6, 'balance' => 0.049993750000000, 'cross_margin' => 0, 'isolated_balance' => 0.000840208333330, 'order_margin' => 0, 'available_balance' => 0.049153541663288],
        ];
        $this->doTest1($inputs, $outputs);

        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12500', 'leverage' => 10],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '50', 'price' => '12500', 'leverage' => 10],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.050169749999983, 'cross_margin' => 0, 'isolated_balance' => 0.000420104166665, 'order_margin' => 0, 'available_balance' => 0.049749645829936],
            ['id' => 6, 'owner_id' => 6, 'balance' => 0.049824083333350, 'cross_margin' => 0, 'isolated_balance' => 0.000420104166665, 'order_margin' => 0, 'available_balance' => 0.049403979163303],
        ];
        $this->doTest1($inputs, $outputs);


        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '25', 'price' => '11500', 'leverage' => 10],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '25', 'price' => '11500', 'leverage' => 10],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'balance' => 0.050170293478244, 'cross_margin' => 0, 'isolated_balance' => 0.000639288949274, 'order_margin' => 0, 'available_balance' => 0.049531004521171],
            ['id' => 6, 'owner_id' => 6, 'balance' => 0.049822452898567, 'cross_margin' => 0, 'isolated_balance' => 0.000639288949274, 'order_margin' => 0, 'available_balance' => 0.049183163941494],
        ];
        $this->doTest1($inputs, $outputs);
    }
}
