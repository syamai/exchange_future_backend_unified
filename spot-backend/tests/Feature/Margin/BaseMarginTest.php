<?php

namespace Tests\Feature\Margin;

use App\Consts;
use App\Models\User;
use App\Utils;
use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class BaseMarginTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();
        $this->clearData();
        $this->clearCache();
    }

    protected function clearData()
    {
    }

    protected function clearCache()
    {
        Cache::flush();
        // Redis::flushall();
        // Redis::connection('order_processor')->flushall();
    }

    protected function setUpInstruments()
    {
        DB::table('instruments')->insert([
            'symbol' => 'BTCUSD',
            'root_symbol' => 'BTC',
            'state' => 'Open',
            'type' => 0,
            'init_margin' => '0.01',
            'maint_margin' => '0.005',
            'multiplier' => -1,
            'tick_size' => '0.5',
            'reference_index' => 'BTC',
            'funding_base_index' => 'BTCBON8H',
            'funding_quote_index' => 'USDBON8H',
            'funding_premium_index' => 'BTCUSDPI8H',
            'funding_interval' => 8,
            'risk_limit' => 200,
            'max_price' => 1000000,
            'max_order_qty' => 1000000,
            'taker_fee' => '0.00075',
            'maker_fee' => '0.00075',
        ]);
        DB::table('instrument_extra_informations')->insert([
            'symbol' => 'BTCUSD',
            'mark_price' => 12000,
        ]);
    }

    protected function setUpAccount($account)
    {
        DB::table('margin_accounts')->insert([
            'id' => $account['id'],
            'balance' => $account['balance'],
            'cross_balance' => $account['balance'],
            'cross_equity' => $account['balance'],
            'cross_margin' => 0,
            'order_margin' => 0,
            'available_balance' => $account['balance'],
            'max_available_balance' => $account['balance'],
        ]);
    }

    protected function setUpInsuranceAccount()
    {
        $userId = User::where('email', Consts::INSURANCE_FUND_EMAIL)->first()->id;
        DB::table('margin_accounts')->insert([
            'id' => 10,
            'balance' => 0,
            'cross_balance' => 0,
            'cross_equity' => 0,
            'cross_margin' => '0',
            'order_margin' => '0',
            'available_balance' => 0,
            'max_available_balance' => 0,
            'owner_id' => $userId,
        ]);
    }

    protected function setUpInstruments1()
    {
        DB::table('instruments')->truncate();
        DB::table('instrument_extra_informations')->truncate();
        Artisan::call('db:seed', ['--class' => 'MarginInstrumentSeeder']);
        Artisan::call('db:seed', ['--class' => 'MarginInsuranceUserSeeder']);
        Artisan::call('db:seed', ['--class' => 'MarketFeeSettingTableSeeder']);
    }

    protected function getOrderData($data)
    {
        if (!array_key_exists('stop_type', $data)) {
            $data['stop_type'] = null;
        }
        $data['remaining'] = $data['quantity'];
        $data['instrument_symbol'] = $this->symbol;
        if (!array_key_exists('time_in_force', $data)) {
            $data['time_in_force'] = Consts::ORDER_TIME_IN_FORCE_GTC;
        }
        if (!array_key_exists('status', $data)) {
            $data['status'] = Consts::ORDER_STATUS_PENDING;
        }
        $data['created_at'] = Utils::currentMilliseconds();
        $data['updated_at'] = Utils::currentMilliseconds();
        return $data;
    }

    protected function updateMarkPrice($price)
    {
        DB::table('instrument_extra_informations')
            ->where('symbol', $this->symbol)
            ->update(['mark_price' => $price]);
    }
}
