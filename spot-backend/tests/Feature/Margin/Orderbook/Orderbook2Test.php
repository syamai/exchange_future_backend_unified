<?php

namespace Tests\Feature\Margin\Orderbook;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use App\Consts;
use App\Utils;
use App\Models\MarginOrder;
use ExecutionService;
use OrderService;
use MatchingEngine;

class Orderbook2Test extends BaseOrderbookTest
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

        Artisan::call('margin:process_order', ['symbol' => 'BTCUSD']);

        $outputs = [
            'buy' => [
            ],
            'sell' => [
                ['price' => '10000', 'quantity' => '1', 'count' => 1],
            ],
        ];

        $this->checkOrderbook($outputs);
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

        $outputs = [
            'buy' => [
                ['price' => '10000', 'quantity' => '2', 'count' => 1],
            ],
            'sell' => [
            ],
        ];

        $this->checkOrderbook($outputs);

        $order->is_canceling = true;
        $order->save();
        MatchingEngine::onOrderCanceled($order);

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
            'buy' => [
            ],
            'sell' => [
                ['price' => '10000', 'quantity' => '1', 'count' => 1],
            ],
        ];

        $this->checkOrderbook($outputs);
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

        $order->is_canceling = true;
        $order->save();
        MatchingEngine::onOrderCanceled($order);

        MatchingEngine::process();

        $outputs = [
            'buy' => [
            ],
            'sell' => [
            ],
        ];

        $this->checkOrderbook($outputs);

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
            'buy' => [
            ],
            'sell' => [
                ['price' => '10000', 'quantity' => '1', 'count' => 1],
            ],
        ];

        $this->checkOrderbook($outputs);
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
        ExecutionService::activeOrder($order2);
        MatchingEngine::onNewOrderCreated($order2);

        $order->is_canceling = true;
        $order->save();
        MatchingEngine::onOrderCanceled($order);

        MatchingEngine::process();

        $outputs = [
            'buy' => [
            ],
            'sell' => [
            ],
        ];

        $this->checkOrderbook($outputs);
    }
}
