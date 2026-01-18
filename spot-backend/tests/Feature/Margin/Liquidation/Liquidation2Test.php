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
use LiquidationService;
use OrderService;
use MarginCalculator;
use Carbon\Carbon;
use App\Models\Position;
use App\Models\User;
use App\Service\Margin\Utils;
use Illuminate\Support\Str as IlluminateStr;

class Liquidation2Test extends BaseMarginTest
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

    protected function updateMarkPrice($price)
    {
        DB::table('instrument_extra_informations')
            ->where('symbol', $this->symbol)
            ->update(['mark_price' => $price]);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group Liquidation
     *
     * @return void
     */
    public function testBankrupt()
    {
        $position = new Position(['is_cross' => '1', 'symbol' => $this->symbol, 'current_qty' => 10000, 'entry_value' => '-1.4886', 'init_margin' => '0.014886']);
        $bankruptPrice = MarginCalculator::getBankruptPrice($position, '0.011914');
        $this->assertSame('6604', $bankruptPrice);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group Liquidation
     *
     * @return void
     */
    public function testBankrupt2()
    {
        $position = new Position(['is_cross' => '1', 'symbol' => $this->symbol, 'current_qty' => -10000, 'entry_value' => '1.4485', 'init_margin' => '0.014485']);
        $bankruptPrice = MarginCalculator::getBankruptPrice($position, '0.006815');
        $this->assertSame('7001', $bankruptPrice);
    }
}
