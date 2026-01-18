<?php

namespace Tests\Feature\Margin\Funding;

use App\Consts;
use App\Service\Margin\Facades\PositionHistoryService;
use App\Models\Position;
use App\Models\PositionHistory;
use Tests\Feature\Margin\BaseMarginTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Service\Margin\Utils;

class FundingTest extends BaseMarginTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->setUpInstruments();
        $this->setUpMarginAccounts();
        $this->setUpFundingRate();
        $this->setUpPositions();
    }

    protected function clearData()
    {
        DB::table('margin_accounts')->truncate();
        DB::table('fundings')->truncate();
        DB::table('positions')->truncate();
        DB::table('margin_processes')->truncate();
        DB::table('instruments')->truncate();
        DB::table('instrument_extra_informations')->truncate();
        DB::table('positions_history')->truncate();
    }

    protected function setUpMarginAccounts()
    {
        DB::table('margin_accounts')->insert([
            'id' => 1,
            'balance' => '100',
            'cross_balance' => '100',
            'cross_equity' => '100',
            'cross_margin' => '0.000889679715300',
            'order_margin' => '0',
            'available_balance' => '99.999110320284700',
            'max_available_balance' => '99.999110320284700',
        ]);
        DB::table('margin_accounts')->insert([
            'id' => 2,
            'balance' => '200',
            'cross_balance' => '200',
            'cross_equity' => '200',
            'cross_margin' => '0.000889679715300',
            'order_margin' => '0',
            'available_balance' => '199.999110320284700',
            'max_available_balance' => '199.999110320284700',
        ]);
    }

    protected function setUpFundingRate()
    {
        $startTime = Utils::getTimeStartFunding();
        $timeExec = Utils::getTimeNearly($startTime, 8 * 60);//8h
        DB::table('fundings')->insert([
            'symbol' => 'BTCUSD',
            'funding_interval' => '8h',
            'funding_rate' => '0.0245',
            'funding_rate_daily' => '0.0735',
            'created_at' => $timeExec,
            'updated_at' => $timeExec,
        ]);
    }

    protected function setUpPositions()
    {
        DB::table('positions')->insert([
            'account_id' => '1',
            'symbol' => 'BTCUSD',
            'leverage' => '100',
            'current_qty' => '1000',
            'init_margin' => '0.000889679715300',
            'maint_margin' => '0.000369217081849',
            'entry_price' => '11240.0000000315',
            'entry_value' => '-0.088967971530000',
            'is_cross' => '1',
        ]);
        DB::table('positions')->insert([
            'account_id' => '2',
            'symbol' => 'BTCUSD',
            'leverage' => '100',
            'current_qty' => '-1000',
            'init_margin' => '0.000889679715300',
            'maint_margin' => '0.000520462633451',
            'entry_price' => '11240.0000000315',
            'entry_value' => '0.088967971530000',
            'is_cross' => '1',
        ]);

        DB::table('positions_history')->insert([
            'position_id' => 1,
            'entry_value_after' => '-0.088967971530000',
            'entry_price_after' => '5000',
            'current_qty_after' => '1000',
            'created_at' => Carbon::yesterday(),
        ]);

        DB::table('positions_history')->insert([
            'position_id' => 2,
            'entry_value_after' => '0.088967971530000',
            'entry_price_after' => '5000',
            'current_qty_after' => '-1000',
            'created_at' => Carbon::yesterday(),
        ]);
    }

    /**
     * Test limit orders
     * @group Margin
     * @group Funding
     *
     * @return void
     */
    public function testFunding0()
    {
        Artisan::call('margin:pay_funding', ['symbol' => 'BTCUSD']);
        $this->assertDatabaseHas('margin_accounts', ['id' => 1, 'balance' => '99.997820284697515']);
        $this->assertDatabaseHas('margin_accounts', ['id' => 2, 'balance' => '200.002179715302485']);
    }
}
