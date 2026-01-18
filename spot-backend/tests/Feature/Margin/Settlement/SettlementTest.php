<?php

namespace Tests\Feature\Margin\Funding;

use Tests\Feature\Margin\BaseMarginTest;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Consts;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ExecutionService;
use IndexService;
use InstrumentService;
use OrderService;
use Carbon\Carbon;

use App\Service\Margin\Utils;

class SettlementTest extends BaseMarginTest
{
    protected $symbol = 'BTCU19';

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpMarginAccounts();
        $this->setUpInstruments();
        $this->setUpIndex();
    }

    protected function clearData()
    {
        DB::table('margin_accounts')->truncate();
        DB::table('margin_orders')->truncate();
        DB::table('positions')->truncate();
        DB::table('margin_processes')->truncate();
        DB::table('instruments')->truncate();
        // DB::table('instrument_extra_informations')->truncate();
        DB::table('settlements')->truncate();
    }

    protected function setUpMarginAccounts()
    {
        $balance = '100';
        DB::table('margin_accounts')->insert([
            'id' => 1,
            'balance' => $balance,
            'cross_balance' => 0,
            'cross_equity' => 0,
            'cross_margin' => 0,
            'order_margin' => 0,
            'available_balance' => $balance,
            'max_available_balance' => $balance,
        ]);
        $balance = '200';
        DB::table('margin_accounts')->insert([
            'id' => 2,
            'balance' => $balance,
            'cross_balance' => 0,
            'cross_equity' => 0,
            'cross_margin' => '0',
            'order_margin' => '0',
            'available_balance' => $balance,
            'max_available_balance' => $balance,
        ]);
        $this->setUpInsuranceAccount();
    }

    protected function setUpInstruments()
    {
        DB::table('instruments')->insert([
            'symbol' => $this->symbol,
            'expiry' => Carbon::now()->addSeconds(1000),
            'root_symbol' => 'BTC',
            'state' => 'Open',
            'type' => 0,
            'init_margin' => '0.01',
            'maint_margin' => '0.005',
            'multiplier' => -1,
            'tick_size' => '0.5',
            'reference_index' => 'BTC',
            'settlement_index' => 'BTC',
            'funding_base_index' => 'BTCBON8H',
            'funding_quote_index' => 'USDBON8H',
            'funding_premium_index' => 'BTCUSDPI8H',
            'funding_interval' => 8,
            'risk_limit' => 200,
            'max_price' => 1000000,
            'max_order_qty' => 10000000,
        ]);
        DB::table('instrument_extra_informations')->insert([
            'symbol' => $this->symbol,
            'mark_price' => 12000,
        ]);
    }

    protected function setUpIndex()
    {
    }

    /**
     * Test limit orders
     * @group Margin
     * @group Settlement
     *
     * @return void
     */
    public function testSettlement0()
    {
        $orders = [
            [
                'account_id' => 1,
                'symbol' => $this->symbol,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1000',
                'price' => '11240',
            ],[
                'account_id' => 1,
                'symbol' => $this->symbol,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1000',
                'price' => '10240',
            ],[
                'account_id' => 2,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1000',
                'price' => '11240',
            ],[
                'account_id' => 2,
                'symbol' => $this->symbol,
                'side' => 'sell',
                'type' => 'limit',
                'quantity' => '1000',
                'price' => '12240',
            ],
        ];
        foreach ($orders as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);


        $this->updateInstrumentAndIndex();

        Artisan::call('margin:settle', ['symbol' => $this->symbol]);

        $this->assertDatabaseHas('margin_orders', ['id' => 2, 'status' => 'canceled']);
        $this->assertDatabaseHas('margin_orders', ['id' => 4, 'status' => 'canceled']);
        $this->assertDatabaseHas('positions', ['account_id' => 1, 'current_qty' => '0']);
        $this->assertDatabaseHas('positions', ['account_id' => 2, 'current_qty' => '0']);
        $this->assertDatabaseHas('margin_accounts', ['id' => 1, 'balance' => '100.002011449791000']);
        $this->assertDatabaseHas('margin_accounts', ['id' => 2, 'balance' => '199.997988550209000']);
    }

    protected function updateInstrumentAndIndex()
    {
        $instrument = InstrumentService::get($this->symbol);
        $instrument->expiry = Carbon::now();
        $instrument->state = Consts::INSTRUMENT_STATE_CLOSE;
        $instrument->save();

        sleep(1);

        IndexService::insert($instrument->reference_index, '11500', $instrument->expiry);
    }
}
