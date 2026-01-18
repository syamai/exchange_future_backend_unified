<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Consts;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Services\MasterdataService;
use App\Http\Services\PriceService;
use App\Models\TradingVolumeRanking;
use App\Models\TradingVolumeRankingTotal;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\Cache;
use App\Http\Services\OrderService;
use App\Models\Order;
use PhpParser\Node\Expr\Cast\Object_;
use App\Models\Settings;
use App\Http\Services\HealthCheckService;

class TradingVolumeRankingCommand extends SpotTradeBaseCommand
{
/**
     * The name and signature of the console command.
     *
     *
     *
     * @var string
     */
    protected $signature = 'trading_volume_ranking_spot:update';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update trading volume ranking when order match';

    protected $isSelf = false;



    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function processTrade($orderTransaction)
    {
        $orderTransaction->volume_type = Consts::TYPE_EXCHANGE_BALANCE;
        $this->isSelf = $this->isSelfTrading($orderTransaction->buyer_id, $orderTransaction->seller_id);
        $this->updateTradingRankingTotal($orderTransaction);
        $this->updateTradingRankingDetail($orderTransaction);
    }


    public function updateTradingRankingDetail($orderTransaction)
    {
        // buyer
        $this->updateTradingRankingByUser($orderTransaction->buyer_id, $orderTransaction->buyer_email, $orderTransaction);

        // seller
        $this->updateTradingRankingByUser($orderTransaction->seller_id, $orderTransaction->seller_email, $orderTransaction);
    }
    public function updateTradingRankingTotal($orderTransaction)
    {
        /// buyer
        $this->updateTradingRankingTotalByUser($orderTransaction->buyer_id, $orderTransaction->buyer_email, $orderTransaction);

        // seller
        $this->updateTradingRankingTotalByUser($orderTransaction->seller_id, $orderTransaction->seller_email, $orderTransaction);
    }

    public function updateTradingRankingByUser($user_id, $email, $orderTransaction)
    {
        if ($this->isBot($user_id, $orderTransaction->coin, $orderTransaction->currency)) {
            return;
        }
        $record = $this->checkExistRecordDaily($user_id, $orderTransaction->coin, $orderTransaction->executed_date, $orderTransaction->currency, $orderTransaction->volume_type);
        if (!$record) {
            $this->createRecord('trading_volume_ranking', $user_id, $email, $orderTransaction->coin, $orderTransaction->quantity, $orderTransaction->amount, $orderTransaction->currency, $orderTransaction->volume_type, $orderTransaction->executed_date);
        } else {
            $this->updateVolume($record, $orderTransaction->quantity, $orderTransaction->currency, $orderTransaction->amount, $orderTransaction->coin);
        }
    }

    public function updateTradingRankingTotalByUser($user_id, $email, $orderTransaction)
    {
        if ($this->isBot($user_id, $orderTransaction->coin, $orderTransaction->currency)) {
            return;
        }
        $record = $this->checkExistRecordTotal($user_id, $orderTransaction->coin, $orderTransaction->volume_type);
        if (!$record) {
            $this->createRecord('trading_volume_ranking_total', $user_id, $email, $orderTransaction->coin, $orderTransaction->quantity, $orderTransaction->amount, $orderTransaction->currency, $orderTransaction->volume_type, $orderTransaction->executed_date);
        } else {
            $this->updateVolume($record, $orderTransaction->quantity, $orderTransaction->currency, $orderTransaction->amount, $orderTransaction->coin);
        }
    }

    public function checkExistRecordDaily($user_id, $coin, $time, $market, $type)
    {
        $record = TradingVolumeRanking::
            where('user_id', $user_id)
            ->where('coin', $coin)
            ->where('market', $market)
            ->where('type', $type)
            ->where('created_at', '=', $time);
        $record = $record->first();
        if (!$record) {
            return false;
        }
        return $record;
    }

    public function checkExistRecordTotal($user_id, $coin, $type)
    {
        $record = TradingVolumeRankingTotal::
            where('user_id', $user_id)
            ->where('coin', $coin)
            ->where('type', $type)
            ->first();
        if (!$record) {
            return false;
        }
        return $record;
    }

    public function getData($table, $user_id, $email, $coin, $volume, $amount, $market, $type, $time)
    {
        $priceService = new PriceService();
        if ($market == Consts::CURRENCY_BTC) {
            $btc_volume = $amount;
        } else {
            $btc_volume = BigNumber::new($volume)->mul($priceService->convertPriceToBTC($coin, false))->toString();
        }
        $selfTrading = 0;
        $selfBtcVolumeTrading = 0;
        if ($this->isSelf) {
            $selfTrading = $volume;
            $selfBtcVolumeTrading = $btc_volume;
        }
        $data = [
            'user_id' => $user_id,
            'email' => $email,
            'coin' => $coin,
            'volume' => $volume,
            'btc_volume' => $btc_volume,
            'self_trading' => $selfTrading,
            'self_trading_btc_volume' => $selfBtcVolumeTrading,
            'trading_volume' => BigNumber::new($btc_volume)->sub($selfBtcVolumeTrading)->toString(),
            'created_at' => $time,
            'updated_at' => Carbon::today()->toDateString(),
            'type' => $type,
        ];
        if ($table == 'trading_volume_ranking') {
            $data['market'] = $market;
        }
        return $data;
    }

    public function createRecord($table, $user_id, $email, $coin, $volume, $amount, $market, $type, $time)
    {
        $data = $this->getData($table, $user_id, $email, $coin, $volume, $amount, $market, $type, $time);
        return DB::table($table)->insert($data);
    }

    public function updateVolume($record, $volume, $market, $amount, $coin)
    {

        $priceService = new PriceService();
        if ($market == Consts::CURRENCY_BTC) {
            $btc_volume = $amount;
        } else {
            $btc_volume = BigNumber::new($volume)->mul($priceService->convertPriceToBTC($coin, false))->toString();
        }
        $record->volume = BigNumber::new($record->volume)->add($volume)->toString();
        $record->btc_volume = BigNumber::new($record->btc_volume)->add($btc_volume)->toString();
        if ($this->isSelf) {
            $record->self_trading = BigNumber::new($record->self_trading)->add($volume)->toString();
            $record->self_trading_btc_volume = BigNumber::new($record->self_trading_btc_volume)->add($btc_volume)->toString();
        }
        $record->trading_volume = BigNumber::new($record->btc_volume)->sub($record->self_trading_btc_volume)->toString();
        $rs = $record->save();
        return $rs;
    }

    protected function getProcessKey(): string
    {
        return 'update_trading_volume_ranking';
    }

    public function isBot($userId, $coin, $currency)
    {
        if (config('app.allow_bot_trading') == 1 && $coin == Consts::CURRENCY_AMAL && $currency == Consts::CURRENCY_USDT) {
            return false;
        }
        $bots = [];
        if (Cache::has('listBots')) {
            $bots = Cache::get('listBots');
        }
        if (in_array($userId, $bots)) {
            return true;
        }
        $user = User::where('id', $userId)->first();
        if (!$user) {
            return true;
        }
        if ($user->type == 'bot') {
            array_push($bots, $userId);
            Cache::put('listBots', $bots);
            return true;
        }
        return false;
    }

    //todo : change key , production diff testnet
    public function isSelfTrading($buyer_id, $seller_id)
    {
        $settings = Settings::where('key', 'self_trading_volume_spot')->first();
        if (!$settings) {
            return false;
        }
        if ($buyer_id == $seller_id) {
            return true;
        }
        return false;
    }
}
