<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\MasterdataService;
use App\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RebuildOrderBook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orderbook:rebuild';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Order Book - Rebuild';



    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('Order Book - Rebuild');

        $coinSettings = MasterdataService::getOneTable('coin_settings');
        foreach ($coinSettings as $coinSetting) {
            $this->recalculateOrderBook($coinSetting->currency, $coinSetting->coin);
        }

        Artisan::call('cache:clear');
    }

    private function recalculateOrderBook($currency, $coin)
    {
        // TODO Thanh Tran: this issue MUST be fixed
        // this function isn't work correctly in highly concurrent condition

        $groups = MasterdataService::getOneTable('price_groups')
            ->where('currency', $currency)
            ->where('coin', $coin);

        DB::table('orderbooks')
            ->where('currency', $currency)
            ->where('coin', $coin)
            ->delete();
        DB::table('user_orderbooks')
            ->where('currency', $currency)
            ->where('coin', $coin)
            ->delete();

        foreach ($groups as $group) {
            $this->info('$group' . json_encode($group));
            $this->info('group->value' . json_encode($group->value));
            $this->recalculateOrderBookPart($currency, $coin, Consts::ORDER_TRADE_TYPE_BUY, $group->value);
            $this->recalculateOrderBookPart($currency, $coin, Consts::ORDER_TRADE_TYPE_SELL, $group->value);
        }
    }

    private function recalculateOrderBookPart($currency, $coin, $tradeType, $price)
    {
        $timestamp = Utils::currentMilliseconds();
        $roundFunction = $tradeType === Consts::ORDER_TRADE_TYPE_BUY ? 'floor' : 'ceil';
        $params = [$currency, $coin, $price, $timestamp, $tradeType, $currency, $coin];
        DB::update("insert into orderbooks"
            . " select trade_type, ?, ?, sum(quantity) as quantity, count(1) as count, $roundFunction(price/$price)*$price, ?, ?"
            . " from orders where trade_type=? and currency=? and coin=? and status='pending' and (type='limit' or type = 'stop_limit')"
            . " group by ($roundFunction(price/$price)*$price), trade_type;", $params);
    }
}
