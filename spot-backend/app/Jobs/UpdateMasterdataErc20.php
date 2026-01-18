<?php

namespace App\Jobs;

use App\Consts;
use App\Http\Services\MasterdataService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Events\UpdateMasterdataErc20 as UpdateMasterdataErc20Event;
use App\Models\AutoDividendSetting;
use App\Models\DividendTotalBonus;

class UpdateMasterdataErc20 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $params;

    /**
     * Create a new job instance.
     *
     * @param $params
     */
    public function __construct($params)
    {
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        event(new UpdateMasterdataErc20Event());
        DB::beginTransaction();
        try {
            $this->updateCoinSetting();
            $this->updateTradingPairSetting();
            $this->updateWithdrawalSetting();

            Artisan::call('cache:clear');

            DB::commit();

            MasterdataService::clearCacheOneTable('trading_limits');
            MasterdataService::clearCacheOneTable('coin_settings');
            MasterdataService::clearCacheOneTable('price_groups');
            MasterdataService::clearCacheOneTable('coins');

            SetMarketPriceErc20::dispatch($this->params)->onQueue(Consts::ADMIN_QUEUE);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            throw $e;
        }
    }

    private function updateWithdrawalSetting()
    {
        $withdrawalSetting = Arr::get($this->params, 'withdrawal_setting', []);

        $currency = Arr::get($withdrawalSetting, 'currency');
        if (DB::table('withdrawal_limits')->where('currency', $currency)->count() === 0) {
            DB::table('withdrawal_limits')->insert([
                'security_level' => 1,
                'currency' => strtolower($currency),
                'limit' => Arr::get($withdrawalSetting, 'limit'),
                'daily_limit' => 0,
                'fee' => Arr::get($withdrawalSetting, 'fee'),
                'minium_withdrawal' => Arr::get($withdrawalSetting, 'minium_withdrawal'),
                'days' => Arr::get($withdrawalSetting, 'days'),
            ]);
            MasterdataService::clearCacheOneTable('withdrawal_limits');
        }
    }

    private function updateTradingPairSetting()
    {
        $tradingPairs = Arr::get($this->params, 'trading_setting', []);

        foreach ($tradingPairs as $tradingPair) {
            $coin = strtolower(Arr::get($tradingPair, 'coin'));
            $currency = strtolower(Arr::get($tradingPair, 'currency'));

            $this->insertTradingFees($coin, $currency, $tradingPair);
            $this->insertTradingLimits($coin, $currency, $tradingPair);
            $this->insertCoinSettings($coin, $currency, $tradingPair);
            $this->insertPriceGroups($coin, $currency, $tradingPair);
        }
    }

    private function insertTradingFees($coin, $currency, $tradingPair)
    {
        if (DB::table('trading_fees')->where('coin', $coin)->where('currency', $currency)->count() > 0) {
            return;
        }
        DB::table('trading_fees')->insert([
            'currency' => $currency,
            'coin' => $coin,
            'fee' => Arr::get($tradingPair, 'fee')
        ]);
        MasterdataService::clearCacheOneTable('trading_fees');
    }

    private function insertTradingLimits($coin, $currency, $tradingPair)
    {
        if (DB::table('trading_limits')->where('coin', $coin)->where('currency', $currency)->count() > 0) {
            return;
        }

        DB::table('trading_limits')->insert([
            'coin' => $coin,
            'currency' => $currency,
            'sell_limit' => Arr::get($tradingPair, 'sell_limit'),
            'buy_limit' => Arr::get($tradingPair, 'buy_limit'),
            'days' => Arr::get($tradingPair, 'days'),
        ]);
    }

    private function insertCoinSettings($coin, $currency, $tradingPair)
    {
        if (DB::table('coin_settings')->where('coin', $coin)->where('currency', $currency)->count() > 0) {
            return;
        }
        DB::table('coin_settings')->insert([
            'coin' => $coin,
            'currency' => $currency,
            'minimum_quantity' => Arr::get($tradingPair, 'minimum_quantity'),
            'quantity_precision' => Arr::get($tradingPair, 'quantity_precision'),
            'price_precision' => Arr::get($tradingPair, 'price_precision'),
            'minimum_amount' => Arr::get($tradingPair, 'minimum_amount'),
        ]);
        $hasCoinInSetting = AutoDividendSetting::where('coin', $coin)->first();
        if ($hasCoinInSetting) {
            return;
        }
        AutoDividendSetting::create([
            'enable' => false,
            'market' => $currency,
            'coin' => $coin,
            'time_from' => null,
            'time_to' => null,
            'payfor' => Consts::TYPE_MAIN_BALANCE,
            'payout_coin' => 'AMAL',
            'payout_amount' => 0,
            'setting_for' => 'spot',
            'lot' => 0
        ]);
        $hasCoinInTotal = DividendTotalBonus::where('coin', $coin)->first();
        if ($hasCoinInTotal) {
            return;
        }
        DividendTotalBonus::create([
            'total_bonus' => 0,
            'coin' => $coin,
        ]);
    }

    private function insertPriceGroups($coin, $currency, $tradingPair)
    {
        if (DB::table('price_groups')->where('coin', $coin)->where('currency', $currency)->count() > 0) {
            return;
        }
        DB::table('price_groups')->insert([
            [
                'coin' => $coin,
                'currency' => $currency,
                'group' => 0,
                'value' => $this->getPriceGroupValue(data_get($tradingPair, 'currency'), 0),
            ],
            [
                'coin' => $coin,
                'currency' => $currency,
                'group' => 1,
                'value' => $this->getPriceGroupValue(data_get($tradingPair, 'currency'), 1),
            ],
            [
                'coin' => $coin,
                'currency' => $currency,
                'group' => 2,
                'value' => $this->getPriceGroupValue(data_get($tradingPair, 'currency'), 2),
            ],
        ]);
    }

    private function getPriceGroupValue($currency, $group)
    {
        $priceGroup = MasterdataService::getOneTable('price_groups')->where('currency', $currency)->where('group', $group)->first();
        return data_get($priceGroup, 'value');
    }

    private function updateCoinSetting()
    {
        $coinSetting = Arr::get($this->params, 'coin_setting', []);
        logger($this->params);
        $coin = strtolower(Arr::get($coinSetting, 'symbol'));
        $network = Arr::get($coinSetting, 'network');

        DB::table('coins')->insert([
            'coin' => $coin,
            'icon_image' => Arr::get($coinSetting, 'image_base64'),
            'name' => Arr::get($coinSetting, 'name'),
            'confirmation' => Arr::get($coinSetting, 'required_confirmations', 12),
            'contract_address' => Arr::get($coinSetting, 'contract_address'),
            'type' => $network ? strtolower($network).'_token' : 'eth_token',
            'trezor_coin_shortcut' => 'eth',
            'trezor_address_path' => 'm/44\'/60\'/{$account}\'/0/{$i}',
            'env' => config('blockchain.network'),
            'transaction_tx_path' => Arr::get($coinSetting, 'explorer', 12) . '/tx/{$transaction_id}',
            'transaction_explorer' => Arr::get($coinSetting, 'explorer', 12),
            'decimal' => Arr::get($coinSetting, 'decimals', 8)
        ]);
    }
}
