<?php

namespace Tests\Feature\Margin\Execution;

use Tests\Feature\Margin\BaseMarginTest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Consts;
use OrderService;
use App\Models\User;
use MatchingEngine;
use ExecutionService;
use App\Service\Margin\MarginBigNumber;

class BaseExecutionTest extends BaseMarginTest
{
    protected $symbol = 'BTCUSD';
    protected $userId = 1;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpInstruments1();
        $insuranceId = User::where('email', Consts::INSURANCE_FUND_EMAIL)->first()->id;
        DB::table('margin_accounts')->insert(['id' => $insuranceId, 'balance' => 0, 'owner_id' => $insuranceId]);
        $this->updateMarkPrice(12000);
    }

    protected function clearData()
    {
        DB::table('margin_accounts')->truncate();
        DB::table('margin_balance_history')->truncate();
        DB::table('margin_orders')->truncate();
        DB::table('margin_trades')->truncate();
        DB::table('positions')->truncate();
        DB::table('positions_history')->truncate();
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
        foreach ($inputs as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);
        foreach ($outputs as $output) {
            $balance = DB::table('margin_accounts')->find($output['id']);
            $position = DB::table('positions')->where('account_id', $output['account_id'])->first();
            if ($position->is_cross) {
                $this->assertEquals($balance->cross_margin, $position->init_margin);
            } else {
                $this->assertEquals($balance->isolated_balance, $position->init_margin);
            }
            $this->assertGreaterThanOrEqual(-0.000000000000001, $balance->order_margin);
            $this->assertGreaterThanOrEqual(-0.000000000000001, $balance->cross_margin);
            $this->assertGreaterThanOrEqual(0, $position->entry_price);
        }
    }

    /**
     * Test order matching
     *
     * @return void
     */
    protected function doTest1($inputs, $outputs)
    {
        foreach ($inputs as $input) {
            $order = OrderService::create($this->getOrderData($input));
            $order['leverage'] = isset($input['leverage']) ? $input['leverage'] : null;
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => 'BTCUSD']);

        foreach ($outputs as $output) {
            $balance = DB::table('margin_accounts')->find($output['id']);
            $this->assertLessThanOrEqual(0.000000000100000, MarginBigNumber::new($balance->balance)->sub($output['balance'])->abs()->toString());
            //$this->assertLessThanOrEqual(0.000000000100000, MarginBigNumber::new($balance->cross_margin)->sub($output['cross_margin'])->abs()->toString());
            $this->assertLessThanOrEqual(0.000000000100000, MarginBigNumber::new($balance->isolated_balance)->sub($output['isolated_balance'])->abs()->toString());
            //$this->assertLessThanOrEqual(0.000000000100000, MarginBigNumber::new($balance->order_margin)->sub($output['order_margin'])->abs()->toString());
            //$this->assertLessThanOrEqual(0.000000000100000, MarginBigNumber::new($balance->available_balance)->sub($output['available_balance'])->abs()->toString());
            //Commented because logic has changed
        }
    }

    /**
     * Test order matching
     *
     * @return void
     */
    protected function assertValue($outputs)
    {
        foreach ($outputs as $output) {
            $balance = DB::table('margin_accounts')->find($output['id']);
            $this->assertLessThanOrEqual(0.000000000100000, MarginBigNumber::new($balance->balance)->sub($output['balance'])->abs()->toString());
            $this->assertLessThanOrEqual(0.000000000100000, MarginBigNumber::new($balance->cross_margin)->sub($output['cross_margin'])->abs()->toString());
            $this->assertLessThanOrEqual(0.000000000100000, MarginBigNumber::new($balance->isolated_balance)->sub($output['isolated_balance'])->abs()->toString());
            //$this->assertLessThanOrEqual(0.000000000100000, MarginBigNumber::new($balance->order_margin)->sub($output['order_margin'])->abs()->toString());
            //$this->assertLessThanOrEqual(0.000000000100000, MarginBigNumber::new($balance->available_balance)->sub($output['available_balance'])->abs()->toString());
            //Commented because logic has changed
        }
    }
}
