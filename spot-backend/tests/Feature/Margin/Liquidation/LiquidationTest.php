<?php

namespace Tests\Feature\Margin\Funding;

use Tests\Feature\Margin\BaseMarginTest;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Consts;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ExecutionService;
use IndexService;
use InstrumentService;
use LiquidationService;
use OrderService;
use MarginCalculator;
use Carbon\Carbon;

use App\Service\Margin\Utils;

class LiquidationTest extends BaseMarginTest
{
    protected $symbol = 'BTCUSD';
    protected $insuranceId = 0;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpMarginAccounts();
        $this->setUpInstruments();
    }

    protected function clearData()
    {
        DB::table('margin_accounts')->truncate();
        DB::table('margin_orders')->truncate();
        DB::table('positions')->truncate();
        DB::table('margin_processes')->truncate();
        DB::table('instruments')->truncate();
        DB::table('instrument_extra_informations')->truncate();
        DB::table('margin_losses')->truncate();
    }

    protected function setUpMarginAccounts()
    {
        $balance = '0.001';
        DB::table('margin_accounts')->insert([
            'id' => 1,
            'owner_id' => 1,
            'balance' => $balance,
            'cross_balance' => $balance,
            'cross_equity' => $balance,
            'cross_margin' => 0,
            'order_margin' => 0,
            'available_balance' => $balance,
            'max_available_balance' => $balance,
        ]);
        $balance = '0.001';
        DB::table('margin_accounts')->insert([
            'id' => 2,
            'owner_id' => 2,
            'balance' => $balance,
            'cross_balance' => $balance,
            'cross_equity' => $balance,
            'cross_margin' => '0',
            'order_margin' => '0',
            'available_balance' => $balance,
            'max_available_balance' => $balance,
        ]);
        $balance = '0.02';
        DB::table('margin_accounts')->insert([
            'id' => 3,
            'owner_id' => 3,
            'balance' => $balance,
            'cross_balance' => $balance,
            'cross_equity' => $balance,
            'cross_margin' => '0',
            'order_margin' => '0',
            'available_balance' => $balance,
            'max_available_balance' => $balance,
        ]);
        $balance = '0.001';
        DB::table('margin_accounts')->insert([
            'id' => 4,
            'owner_id' => 4,
            'balance' => $balance,
            'cross_balance' => $balance,
            'cross_equity' => $balance,
            'cross_margin' => '0',
            'order_margin' => '0',
            'available_balance' => $balance,
            'max_available_balance' => $balance,
        ]);
        $balance = '0';
        $user = User::where('email', Consts::INSURANCE_FUND_EMAIL)->first();
        if (!$user) {
            User::insert([
                'name' => "Insurance Fund",
                'email' => Consts::INSURANCE_FUND_EMAIL,
                'password' => '',
                'remember_token' => IlluminateStr::random(10),
                'type' => 'bot',
                'status' => 'active'
            ]);

            DB::table('margin_accounts')->insert([
                'balance' => $balance,
                'cross_balance' => $balance,
                'cross_equity' => $balance,
                'cross_margin' => '0',
                'order_margin' => '0',
                'available_balance' => $balance,
                'max_available_balance' => $balance,
                'owner_id' => User::where('email', Consts::INSURANCE_FUND_EMAIL)->first()->id,
            ]);
        } else {
            DB::table('margin_accounts')->insert([
                'balance' => $balance,
                'cross_balance' => $balance,
                'cross_equity' => $balance,
                'cross_margin' => '0',
                'order_margin' => '0',
                'available_balance' => $balance,
                'max_available_balance' => $balance,
                'owner_id' => $user->id,
            ]);
        }
        $this->insuranceId = LiquidationService::getInsuranceFundId();
    }

    /**
     * Test limit orders
     * @group Margin
     * @group Liquidation
     *
     * @return void
     */
    public function testLiquidation0()
    {
        $this->updateMarkPrice(11240);
        $orders = [
            [
                'account_id' => 1,
                'symbol' => $this->symbol,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '100',
                'price' => '11240',
            ],[
                'account_id' => 2,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '100',
                'price' => '11240',
            ],
        ];
        foreach ($orders as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        $this->updateMarkPrice(13000);
        MarginCalculator::setInstrument($this->symbol);

        Artisan::call('margin:liquid_check_positions');

        $outputs = [
            ['account_id' => 1, 'liquidation_progress' => 0],
            ['account_id' => 2, 'liquidation_progress' => Consts::LIQUIDATION_PROGRESS_STARTED],
        ];
        $this->checkPositions($outputs);
    }

    private function setInsuranceFundBalance($balance)
    {
        DB::table('margin_accounts')
            ->where('id', LiquidationService::getInsuranceFundId())
            ->update([
                'balance' => $balance,
                'available_balance' => $balance,
                'max_available_balance' => $balance,
            ]);
    }

    private function checkPositions($outputs)
    {
        foreach ($outputs as $output) {
            $this->assertDatabaseHas('positions', $output);
        }
    }

    /**
     * Test limit orders
     * @group Margin
     * @group Liquidation
     *
     * @return void
     */
    public function testLiquidation1()
    {
        $this->updateMarkPrice(11240);
        $orders = [
            [
                'account_id' => 1,
                'symbol' => $this->symbol,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '900',
                'price' => '11240',
            ],[
                'account_id' => 2,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '900',
                'price' => '11240',
            ],
        ];
        foreach ($orders as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);


        $this->updateMarkPrice(10000);
        MarginCalculator::setInstrument($this->symbol);

        Artisan::call('margin:liquid_check_positions');

        $outputs = [
            ['account_id' => 1, 'liquidation_progress' => Consts::LIQUIDATION_PROGRESS_STARTED],
            ['account_id' => 2, 'liquidation_progress' => 0],
        ];
        $this->checkPositions($outputs);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group Liquidation
     *
     * @return void
     */
    public function testLiquidation2()
    {
        $this->updateMarkPrice(11240);
        $orders = [
            [
                'account_id' => 1,
                'symbol' => $this->symbol,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],[
                'account_id' => 2,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],[
                'account_id' => 2,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '150',
                'price' => '12240',
            ],
        ];
        foreach ($orders as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);


        $this->updateMarkPrice(13000);
        MarginCalculator::setInstrument($this->symbol);

        Artisan::call('margin:liquid_check_positions');
        Artisan::call('margin:liquid_close_positions_market');
        Artisan::call('margin:liquid_check_positions');
        Artisan::call('margin:liquid_close_positions_market');
        // Artisan::call('margin:liquid_close_positions_insurance');
        // Artisan::call('margin:liquid_close_insurance_position');
        // Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        $outputs = [
            ['account_id' => 1, 'liquidation_progress' => 0],
            ['account_id' => 2, 'liquidation_progress' => Consts::LIQUIDATION_PROGRESS_CLOSING],
        ];
        $this->checkPositions($outputs);
        $this->assertDatabaseHas('margin_orders', ['id' => 3, 'status' => Consts::ORDER_STATUS_CANCELED]);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group Liquidation
     *
     * @return void
     */
    public function testLiquidation3()
    {
        $this->updateMarkPrice(11240);
        $orders = [
            [
                'account_id' => 1,
                'symbol' => $this->symbol,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],[
                'account_id' => 2,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],[
                'account_id' => 3,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1000',
                'price' => '11300',
            ],
        ];
        foreach ($orders as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        $this->updateMarkPrice(13000);
        MarginCalculator::setInstrument($this->symbol);

        Artisan::call('margin:liquid');

        $outputs = [
            ['account_id' => 1, 'liquidation_progress' => 0],
            ['account_id' => 2, 'liquidation_progress' => Consts::LIQUIDATION_PROGRESS_CLOSING],
        ];
        $this->checkPositions($outputs);

        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);
        $this->assertDatabaseHas('positions', ['account_id' => 2, 'current_qty' => '0']);
        $this->assertDatabaseHas('positions', ['account_id' => 3, 'current_qty' => '-400']);

        // $insuranceBalance = DB::table('margin_accounts')->find($this->insuranceId);
        // $this->assertGreaterThan('0', $insuranceBalance->balance);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group Liquidation
     *
     * @return void
     */
    public function testLiquidation4()
    {
        $this->updateMarkPrice(11240);
        $this->setInsuranceFundBalance(1);
        $orders = [
            [
                'account_id' => 1,
                'symbol' => $this->symbol,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],[
                'account_id' => 2,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],
        ];
        foreach ($orders as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        $this->updateMarkPrice(13000);
        MarginCalculator::setInstrument($this->symbol);

        Artisan::call('margin:liquid');

        $outputs = [
            ['account_id' => 1, 'liquidation_progress' => 0],
            ['account_id' => 2, 'liquidation_progress' => Consts::LIQUIDATION_PROGRESS_CLOSING],
        ];
        $this->checkPositions($outputs);

        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        Artisan::call('margin:liquid');

        $this->assertDatabaseHas('positions', ['account_id' => 2, 'current_qty' => '0']);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group Liquidation
     *
     * @return void
     */
    public function testLiquidation5()
    {
        $this->updateMarkPrice(11240);
        $this->setInsuranceFundBalance('0.001');
        $orders = [
            [
                'account_id' => 1,
                'symbol' => $this->symbol,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],[
                'account_id' => 2,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],
        ];
        foreach ($orders as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        $this->updateMarkPrice(13000);
        MarginCalculator::setInstrument($this->symbol);

        Artisan::call('margin:liquid');

        $outputs = [
            ['account_id' => 1, 'liquidation_progress' => 0],
            ['account_id' => 2, 'liquidation_progress' => Consts::LIQUIDATION_PROGRESS_CLOSING],
        ];
        $this->checkPositions($outputs);

        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        Artisan::call('margin:liquid');

        $this->assertDatabaseHas('positions', ['account_id' => 2, 'current_qty' => '0']);
        $this->assertDatabaseHas('positions', ['account_id' => $this->insuranceId, 'current_qty' => '-97']);

        $this->assertDatabaseHas('margin_losses', ['symbol' => 'BTCUSD', 'position_id' => '2', 'loss' => '-303']);
        Artisan::call('margin:deleverage');
        $this->assertDatabaseHas('margin_losses', ['symbol' => 'BTCUSD', 'position_id' => '2', 'status' => 'processed']);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group Liquidation
     *
     * @return void
     */
    public function testLiquidation6()
    {
        $this->updateMarkPrice(11240);
        $this->setInsuranceFundBalance(1);
        $orders = [
            [
                'account_id' => 1,
                'symbol' => $this->symbol,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],[
                'account_id' => 2,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],[
                'account_id' => 3,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '13000',
            ],
        ];
        foreach ($orders as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        $this->updateMarkPrice(13000);
        MarginCalculator::setInstrument($this->symbol);

        Artisan::call('margin:liquid');

        $outputs = [
            ['account_id' => 1, 'liquidation_progress' => 0],
            ['account_id' => 2, 'liquidation_progress' => Consts::LIQUIDATION_PROGRESS_CLOSING],
        ];
        $this->checkPositions($outputs);

        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        Artisan::call('margin:liquid');
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        $this->assertDatabaseHas('positions', ['account_id' => 2, 'current_qty' => '0']);
        $this->assertDatabaseHas('positions', ['account_id' => 3, 'current_qty' => '-400']);
        $this->assertDatabaseHas('positions', ['account_id' => $this->insuranceId, 'current_qty' => '0']);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group Liquidation
     *
     * @return void
     */
    public function testLiquidation7()
    {
        $this->updateMarkPrice(11240);
        $this->setInsuranceFundBalance('0.001');
        $orders = [
            [
                'account_id' => 1,
                'symbol' => $this->symbol,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],[
                'account_id' => 2,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],
            [
                'account_id' => 3,
                'symbol' => $this->symbol,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],[
                'account_id' => 4,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],
        ];
        foreach ($orders as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        $this->updateMarkPrice(13000);
        MarginCalculator::setInstrument($this->symbol);

        Artisan::call('margin:liquid');

        $outputs = [
            ['account_id' => 1, 'liquidation_progress' => 0],
            ['account_id' => 2, 'liquidation_progress' => Consts::LIQUIDATION_PROGRESS_CLOSING],
        ];
        $this->checkPositions($outputs);

        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        Artisan::call('margin:liquid');

        $this->assertDatabaseHas('positions', ['account_id' => 2, 'current_qty' => '0']);
        $this->assertDatabaseHas('positions', ['account_id' => $this->insuranceId, 'current_qty' => '-97']);

        $this->assertDatabaseHas('margin_losses', ['symbol' => 'BTCUSD', 'position_id' => '2', 'loss' => '-303']);
        Artisan::call('margin:deleverage');
        $this->assertDatabaseHas('margin_losses', ['symbol' => 'BTCUSD', 'position_id' => '2', 'status' => 'processed']);
    }


    /**
     * Test limit orders
     * @group Margin
     * @group Liquidation
     *
     * @return void
     */
    public function testLiquidation8()
    {
        $this->updateMarkPrice(11240);
        $this->setInsuranceFundBalance(1);
        $orders = [
            [
                'account_id' => 1,
                'symbol' => $this->symbol,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],[
                'account_id' => 2,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11240',
            ],[
                'account_id' => 3,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '400',
                'price' => '11300',
            ],
        ];
        foreach ($orders as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        $this->updateMarkPrice(13000);
        MarginCalculator::setInstrument($this->symbol);

        Artisan::call('margin:liquid');

        $outputs = [
            ['account_id' => 1, 'liquidation_progress' => 0],
            ['account_id' => 2, 'liquidation_progress' => Consts::LIQUIDATION_PROGRESS_CLOSING],
        ];
        $this->checkPositions($outputs);

        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        Artisan::call('margin:liquid');
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);

        $this->assertDatabaseHas('margin_accounts', ['id' => $this->insuranceId, 'balance' => '1.000757197785600']);
        $this->assertDatabaseHas('positions', ['account_id' => 2, 'current_qty' => '0']);
        $this->assertDatabaseHas('positions', ['account_id' => 3, 'current_qty' => '-400']);
    }
}
