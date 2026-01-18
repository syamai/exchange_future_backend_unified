<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Consts;
use App\Models\Process;
use Carbon\Carbon;
use App\Http\Services\PriceService;
use App\Models\TradingVolumeRanking;
use App\Models\TradingVolumeRankingTotal;
use App\Utils\BigNumber;
use App\Models\Settings;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class SpotTradingVolumeForUser extends Command
{
    /**
     * Do not run this command directly.
     *
     * @var string
     */
    protected $signature = 'spot:update_trading_user {user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update by trade';

    protected $process;
    protected $user_id;
    protected $isSelf;

    public function handle()
    {
        $this->user_id = $this->argument('user_id');
        $this->process = Process::firstOrCreate(['key' => $this->getProcessKey()]);
        $this->updateVolumeZero($this->user_id);
        $this->updateVolumeZeroTotal($this->user_id);
        $this->doJob();
    }

    public function updateVolumeZero($user_id)
    {
        return TradingVolumeRanking::where('user_id', $user_id)->update([

            'self_trading' => 0,
            'self_trading_btc_volume' => 0,
            'btc_volume' => 0,
            'trading_volume' => 0,
            'volume' => 0,
        ]);
    }

    public function updateVolumeZeroTotal($user_id)
    {
        return TradingVolumeRankingTotal::where('user_id', $user_id)->update([

            'self_trading' => 0,
            'self_trading_btc_volume' => 0,
            'btc_volume' => 0,
            'trading_volume' => 0,
            'volume' => 0,
        ]);
    }

    protected function doJob()
    {

        $email = $this->getEmail($this->user_id);
        $shouldContinue = true;
        while ($shouldContinue) {
            DB::connection('master')->transaction(function () use (&$shouldContinue, $email) {
                $process = Process::on('master')->lockForUpdate()->find($this->process->id);
                $trade = $this->getNextTrade($process);

                if (!$trade) {
                    $shouldContinue = false;
                    return;
                }

                $this->processTrade($trade, $this->user_id, $email);

                $process->processed_id = $trade->id;
                $process->save();
                $this->process = $process;
            });
        }
    }

    protected function getProcessKey(): string
    {
        return "update_trading_volume_for_user_" . $this->user_id;
    }

    public function getEmail($user_id)
    {
        $user = User::where('id', $user_id)->first();
        return $user->email;
    }

    protected function processTrade($orderTransaction, $user_id, $email)
    {
        $orderTransaction->volume_type = Consts::TYPE_EXCHANGE_BALANCE;
        $this->isSelf = $this->isSelfTrading($orderTransaction->buyer_id, $orderTransaction->seller_id);
        $this->updateTradingRankingTotal($orderTransaction, $user_id, $email);
        $this->updateTradingRankingDetail($orderTransaction, $user_id, $email);
    }
    public function updateTradingRankingDetail($orderTransaction, $user_id, $email)
    {
        $this->updateTradingRankingByUser($user_id, $email, $orderTransaction);
    }
    public function updateTradingRankingTotal($orderTransaction, $user_id, $email)
    {
        $this->updateTradingRankingTotalByUser($user_id, $email, $orderTransaction);
    }

    public function updateTradingRankingByUser($user_id, $email, $orderTransaction)
    {
        $record = $this->checkExistRecordDaily($user_id, $orderTransaction->coin, $orderTransaction->executed_date, $orderTransaction->currency, $orderTransaction->volume_type);
        if (!$record) {
            $this->createRecord('trading_volume_ranking', $user_id, $email, $orderTransaction->coin, $orderTransaction->quantity, $orderTransaction->amount, $orderTransaction->currency, $orderTransaction->volume_type, $orderTransaction->executed_date);
        } else {
            $this->updateVolume($record, $orderTransaction->quantity, $orderTransaction->currency, $orderTransaction->amount, $orderTransaction->coin);
        }
    }

    public function updateTradingRankingTotalByUser($user_id, $email, $orderTransaction)
    {
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
            ->where('created_at', $time);
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

    public function getData($table, $user_id, $email, $coin, $volume, $amount, $market, $type, $time): array
    {
        $rate = 1;
        if ($this->isSelf) {
            $rate = 2;
        }
        $amount = BigNumber::new($amount)->mul($rate)->toString();
        $volume = BigNumber::new($volume)->mul($rate)->toString();
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
            'trading_volume' => BigNumber::new($btc_volume)->sub($selfBtcVolumeTrading)->mul($rate)->toString(),
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
        $rate = 1;
        if ($this->isSelf) {
            $rate = 2;
        }
        $amount = BigNumber::new($amount)->mul($rate)->toString();
        $volume = BigNumber::new($volume)->mul($rate)->toString();
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

    public function isSelfTrading($buyer_id, $seller_id): bool
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

    protected function getNextTrade($process)
    {
        return DB::table('order_transactions')
            ->where('id', '>', $process->processed_id)
            ->where(function ($q) {
                $q->orWhere('buyer_id', $this->user_id);
                $q->orWhere('seller_id', $this->user_id);
            })
            ->orderBy('id', 'asc')
            ->first();
    }
}
