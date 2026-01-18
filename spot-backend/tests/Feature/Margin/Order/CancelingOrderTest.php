<?php

namespace Tests\Feature\Margin\Order;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use App\Consts;
use ExecutionService;
use OrderService;
use MatchingEngine;

class CancelingOrderTest extends BaseOrderTest
{

    /**
     * Test limit orders
     * @group Margin
     * @group MarginCanceling
     *
     * @return void
     */
    public function testOrder0()
    {
        MatchingEngine::initialize($this->symbol);

        $order = OrderService::create($this->getOrderData([
            'account_id' => 1,
            'side' => 'buy',
            'type' => 'limit',
            'quantity' => '2',
            'price' => '10000',
        ]));
        ExecutionService::activeOrder($order);
        MatchingEngine::onNewOrderCreated($order);

        $order2 = OrderService::create($this->getOrderData([
            'account_id' => 1,
            'side' => 'sell',
            'type' => 'limit',
            'quantity' => '1',
            'price' => '10000',
        ]));

        MatchingEngine::onNewOrderCreated($order2);

        MatchingEngine::process();

        $outputs = [
            ['id' => $order->id, 'remaining' => 1, 'status' => Consts::ORDER_STATUS_EXECUTING],
            ['id' => $order2->id, 'remaining' => 0, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        foreach ($outputs as $output) {
            $this->assertDatabaseHas('margin_orders', $output);
        }
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginCanceling
     *
     * @return void
     */
    public function testOrder1()
    {
        $order = OrderService::create($this->getOrderData([
            'account_id' => 1,
            'side' => 'buy',
            'type' => 'limit',
            'quantity' => '2',
            'price' => '10000',
        ]));
        ExecutionService::activeOrder($order);

        OrderService::cancel($order);

        OrderService::create($this->getOrderData([
            'account_id' => 1,
            'side' => 'sell',
            'type' => 'limit',
            'quantity' => '1',
            'price' => '10000',
        ]));
        ExecutionService::activeOrder($order);

        Artisan::call('margin:process_order', ['symbol' => 'BTCUSD']);

        $outputs = [
            ['id' => 1, 'status' => Consts::ORDER_STATUS_CANCELED],
            ['id' => 2, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        foreach ($outputs as $output) {
            $this->assertDatabaseHas('margin_orders', $output);
        }
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginCanceling
     *
     * @return void
     */
    public function testOrder2()
    {
        MatchingEngine::initialize($this->symbol);

        $order = OrderService::create($this->getOrderData([
            'account_id' => 1,
            'side' => 'buy',
            'type' => 'limit',
            'quantity' => '2',
            'price' => '10000',
        ]));
        ExecutionService::activeOrder($order);
        MatchingEngine::onNewOrderCreated($order);

        MatchingEngine::onOrderCanceled($order);

        MatchingEngine::process();

        $order = OrderService::create($this->getOrderData([
            'account_id' => 1,
            'side' => 'sell',
            'type' => 'limit',
            'quantity' => '1',
            'price' => '10000',
        ]));
        ExecutionService::activeOrder($order);
        MatchingEngine::onNewOrderCreated($order);

        MatchingEngine::process();

        $outputs = [
            ['id' => $order->id, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        foreach ($outputs as $output) {
            $this->assertDatabaseHas('margin_orders', $output);
        }
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginCanceling
     *
     * @return void
     */
    public function testOrder3()
    {
        MatchingEngine::initialize($this->symbol);

        $order = OrderService::create($this->getOrderData([
            'account_id' => 1,
            'side' => 'buy',
            'type' => 'limit',
            'quantity' => '2',
            'price' => '10000',
        ]));
        ExecutionService::activeOrder($order);
        MatchingEngine::onNewOrderCreated($order);

        MatchingEngine::process();

        MatchingEngine::onOrderCanceled($order);

        $order2 = OrderService::create($this->getOrderData([
            'account_id' => 1,
            'side' => 'sell',
            'type' => 'limit',
            'quantity' => '1',
            'price' => '10000',
        ]));
        ExecutionService::activeOrder($order2);
        MatchingEngine::onNewOrderCreated($order2);

        MatchingEngine::process();

        $outputs = [
            ['id' => $order2->id, 'status' => Consts::ORDER_STATUS_PENDING],
        ];
        foreach ($outputs as $output) {
            $this->assertDatabaseHas('margin_orders', $output);
        }
    }

    /**
     * Test limit orders
     * @group Margin
     * @group MarginCanceling
     *
     * @return void
     */
    public function testOrder4()
    {
        MatchingEngine::initialize($this->symbol);

        $order = OrderService::create($this->getOrderData([
            'account_id' => 1,
            'side' => 'buy',
            'type' => 'limit',
            'quantity' => '2',
            'price' => '10000',
        ]));
        ExecutionService::activeOrder($order);
        MatchingEngine::onNewOrderCreated($order);
        MatchingEngine::process();

        $order2 = OrderService::create($this->getOrderData([
            'account_id' => 1,
            'side' => 'sell',
            'type' => 'limit',
            'quantity' => '1',
            'price' => '10000',
        ]));
        MatchingEngine::onNewOrderCreated($order2);
        MatchingEngine::onOrderCanceled($order2);
        MatchingEngine::process();

        $outputs = [
            ['id' => $order->id, 'status' => Consts::ORDER_STATUS_EXECUTING],
            ['id' => $order2->id, 'status' => Consts::ORDER_STATUS_EXECUTED],
        ];
        foreach ($outputs as $output) {
            $this->assertDatabaseHas('margin_orders', $output);
        }
    }
}
