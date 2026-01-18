<?php

namespace Tests\Feature\Margin\Execution;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\MarginOrder;
use OrderService;
use MatchingEngine;

class ExecutionTest extends BaseExecutionTest
{

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution0
     *
     * @return void
     */
    public function testOrder0()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution1
     *
     * @return void
     */

    public function testOrder1()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'market', 'quantity' => '100'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution2
     *
     * @return void
     */

    public function testOrder2()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'market', 'quantity' => '100'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution3
     *
     * @return void
     */

    public function testOrder3()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '150', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution4
     *
     * @return void
     */

    public function testOrder4()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '150', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution5
     *
     * @return void
     */

    public function testOrder5()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution6
     *
     * @return void
     */

    public function testOrder6()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '150', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '150', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution7
     *
     * @return void
     */

    public function testOrder7()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution8
     *
     * @return void
     */

    public function testOrder8()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'market', 'quantity' => '100'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution9
     *
     * @return void
     */

    public function testOrder9()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'market', 'quantity' => '100'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'market', 'quantity' => '100'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution10
     *
     * @return void
     */

    public function testOrder10()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution11
     *
     * @return void
     */

    public function testOrder11()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution12
     *
     * @return void
     */

    public function testOrder12()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '70', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution13
     *
     * @return void
     */

    public function testOrder13()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'market', 'quantity' => '100'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution14
     *
     * @return void
     */

    public function testOrder14()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'market', 'quantity' => '100'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'market', 'quantity' => '50'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution15
     *
     * @return void
     */

    public function testOrder15()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'market', 'quantity' => '100'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'market', 'quantity' => '100'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '50', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution16
     *
     * @return void
     */

    public function testOrder16()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '11000'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '11000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution17
     *
     * @return void
     */

    public function testOrder17()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 100]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 100]);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '10'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '10', 'price' => '10'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution18
     *
     * @return void
     */

    public function testOrder18()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 100]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 100]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '16000'],
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '9000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution19
     *
     * @return void
     */

    public function testOrder19()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 100]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 100]);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '86', 'price' => '13662'],
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '90', 'price' => '5426'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '90', 'price' => '5426'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '33', 'price' => '10523', 'lock_price' => '7651'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '23', 'price' => '7651'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '10', 'price' => '8683'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);

        $inputs = [
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '36', 'price' => '13662'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '81', 'price' => '9163'],
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '51', 'price' => '14066', 'lock_price' => '12469'],
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '51', 'price' => '12469'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '44', 'price' => '13141'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '7', 'price' => '13223'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);


        $inputs = [
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '50', 'price' => '13622'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);

        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '9123'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '2', 'price' => '9960'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '3', 'price' => '9366'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '45', 'price' => '9163'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution20
     *
     * @return void
     */

    public function testOrder20()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 100]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 100]);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '86', 'price' => '13662'],
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '90', 'price' => '5426'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '90', 'price' => '5426'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '33', 'price' => '7651'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '23', 'price' => '7651'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '10', 'price' => '8683'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);

        $inputs = [
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '36', 'price' => '13662'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '81', 'price' => '9163'],
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '51', 'price' => '12469'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '44', 'price' => '13141'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '7', 'price' => '13223'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);


        $inputs = [
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '50', 'price' => '13622'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);

        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '9123'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '2', 'price' => '9960'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '3', 'price' => '9366'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '45', 'price' => '9163'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution21
     *
     * @return void
     */

    public function testOrder21()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 100]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 100]);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '10000', 'leverage' => 1],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '50', 'price' => '10000', 'leverage' => 1],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);

        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '10000', 'leverage' => 1],
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '50', 'price' => '12000', 'leverage' => 1],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '10000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution22
     *
     * @return void
     */

    public function testOrder22()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 100]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 100]);
        $inputs = [
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '13', 'price' => '12275', 'leverage' => 1],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '64', 'price' => '11927', 'leverage' => 1],
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '99', 'price' => '11685', 'leverage' => 1],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);

        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '42', 'price' => '12121', 'leverage' => 1],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '22', 'price' => '11685', 'leverage' => 1],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '34', 'price' => '12121', 'leverage' => 1],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '8', 'price' => '12121', 'leverage' => 1],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution23
     *
     * @return void
     */

    public function testOrder23()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 100]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 100]);
        $inputs = [
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000', 'leverage' => 1],
            ['account_id' => 5, 'side' => 'buy', 'type' => 'market', 'quantity' => '100', 'leverage' => 1],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);

        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '50', 'price' => '13000', 'leverage' => 1],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);

        $inputs = [
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000', 'leverage' => 1],
            ['account_id' => 5, 'side' => 'sell', 'type' => 'market', 'quantity' => '100', 'leverage' => 1],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution24
     *
     * @return void
     */

    public function testOrder24()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 100]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 100]);

        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '3', 'price' => '1000'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '3', 'price' => '1000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);

        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '4', 'price' => '100'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution25
     *
     * @return void
     */
    public function testOrder25()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
        ];
        $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution26
     *
     * @return void
     */
    public function testOrder26()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.05]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '13000', 'is_reduce_only' => 1],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '200', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '200', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        // $inputs = [
        //     ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '13000'],
        // ];
        // $this->doTest($inputs, $outputs);
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution27
     *
     * @return void
     */
    public function testOrder27()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 0.05]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 0.005]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '10000', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'market', 'quantity' => '10000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);
        $order1 = MarginOrder::find(1);
        $order2 = MarginOrder::find(2);
        $this->assertEquals($order1->status, 'executing');
        $this->assertEquals($order2->status, 'executed');
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution28
     *
     * @return void
     */
    public function testOrder28()
    {
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 5]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 5]);
        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '12000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);

        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '100', 'price' => '10000'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '100', 'price' => '10000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);

        // $order1 = MarginOrder::find(1);
        // $order2 = MarginOrder::find(2);
        // $this->assertEquals($order1->status, 'executing');
        // $this->assertEquals($order2->status, 'executed');
    }

    /**
     * Test execution
     * @group Margin
     * @group MarginExecution
     * @group MarginExecution29
     *
     * @return void
     */
    public function testOrder29()
    {
        $this->updateMarkPrice(10000);
        $this->setUpAccount(['id' => 5, 'owner_id' => 5, 'balance' => 5]);
        $this->setUpAccount(['id' => 6, 'owner_id' => 6, 'balance' => 5]);

        $inputs = [
            ['account_id' => 5, 'side' => 'sell', 'type' => 'limit', 'quantity' => '1900000', 'price' => '10000'],
            ['account_id' => 6, 'side' => 'buy', 'type' => 'limit', 'quantity' => '1900000', 'price' => '10000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);

        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'limit', 'quantity' => '4000000', 'price' => '9700'],
            ['account_id' => 6, 'side' => 'sell', 'type' => 'limit', 'quantity' => '1900000', 'price' => '9760'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);

        \DB::table('positions')->where('id', 1)->update(['closed_id' => 5]);

        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'market', 'quantity' => '1900000'],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);

        \DB::table('positions')->where('id', 1)->update(['closed_id' => 6]);

        $inputs = [
            ['account_id' => 5, 'side' => 'buy', 'type' => 'market', 'quantity' => '1900000', 'is_reduce_only' => 1],
        ];
        $outputs = [
            ['id' => 5, 'owner_id' => 5, 'account_id' => 5],
            ['id' => 6, 'owner_id' => 6, 'account_id' => 6],
        ];
        $this->doTest($inputs, $outputs);

        $position = \DB::table('positions')->first();
        $this->assertEquals($position->closed_id, null);
    }
}
