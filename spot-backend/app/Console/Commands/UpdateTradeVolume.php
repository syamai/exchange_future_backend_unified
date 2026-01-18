<?php
namespace App\Console\Commands;

use App\Models\TradeVolumeStatistic;
use App\Models\AirdropHistoryLockBalance;
use App\Models\User;
use App\Models\AutoDividendSetting;
use App\Consts;
use App\Http\Services\AirdropService;
use App\Http\Services\PriceService;
use App\Jobs\SendBonusByAdminJob;
use App\Models\Airdrop\AutoDividendHistory;
use App\Models\TotalBonusEachPair;
use App\Utils;
use App\Utils\BigNumber;
use App\Models\Settings;
use App\Http\Services\SettingService;
use Carbon\Carbon;
use App\Http\Services\HealthCheckService;

class UpdateTradeVolume extends SpotTradeBaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:trade_volume';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Trading Volume';
    protected $process;

    protected $isSelfTrading;
    protected $settingSelfTrading;
    protected $kyc;
    protected array $bonusOption = [0,1];
    protected array $trade = [];
    protected int $totalPaid = 0;

    protected function processTrade($trade)
    {
        $healthcheck = new HealthCheckService(Consts::HEALTH_CHECK_SERVICE_DIVIDEND, Consts::HEALTH_CHECK_DOMAIN_SPOT);
        $healthcheck->startLog();
        $this->totalPaid = 0;
        if ($this->checkMaxBonus($trade->coin, $trade->currency) == false) {
            return;
        }
        $this->trade = $trade;
        $priceService = new PriceService();
        $amount = BigNumber::new($trade->quantity)->mul($priceService->convertPriceToBTC($trade->coin, false))->toString();

        $settingService = new SettingService();
        $this->kyc = $settingService->getValueFromKey('dividend_kyc_spot');
        $this->settingSelfTrading = $settingService->getValueFromKey('self_trading_auto_dividend_spot');
        if (!$this->isSelfTrading($trade->buyer_id, $trade->seller_id)) {
            return ;
        }
        $this->updateVolume($trade->buyer_id, $trade->coin, $trade->currency, $amount, $trade->id, 0);
        $this->updateVolume($trade->seller_id, $trade->coin, $trade->currency, $amount, $trade->id, 1);
        $healthcheck->endLog();
    }

    public function checkMaxBonus($coin, $currency): bool
    {
        $setting = AutoDividendSetting::where('market', $currency)
            ->where('coin', $coin)
            ->first();
        if (!$setting) {
        	return false;
		}
        $currencyTotalBonus = TotalBonusEachPair::where('currency', $currency)
            ->where('coin', $coin)
            ->where('payout_coin', $setting->payout_coin)
            ->first();
        if (!$currencyTotalBonus) {
            $createRecord = app(AirdropService::class)->createTotalPaidEachPair($coin, $currency, $setting->payout_coin);
            if (!$createRecord) {
                return false;
            }
        } else {
            $this->totalPaid = $currencyTotalBonus->total_paid;
        }
        if (BigNumber::new($setting->max_bonus)->comp($this->totalPaid) <= 0) {
            return false;
        }
        return true;
    }

    public function updateVolume($userId, $coin, $market, $amount, $transactionId, $userSecond): ?bool
    {
        $kyc_user = Utils::checkKycUser($userId);
        if (($this->kyc) && (!$kyc_user)) {
            return null;
        }
        $setting = $this->getSetting($coin, $market);
        if (!$setting || $setting->lot == 0) {
            return null;
        }
        $timeCondition = $this->checkTimeCondition($setting);
        if (!$timeCondition) {
            return null;
        }
        $excessVolume = $this->getExcessVolume($userId, $coin, $market);
        if (BigNumber::new($amount)->add($excessVolume)->comp($setting->lot) >= 0) {
            if ($userSecond == 0) {
                $this->updateBonusOption($amount, $setting->lot);
            }
            return $this->payBonus($userId, $amount, $excessVolume, $coin, $market, $setting, $transactionId, $userSecond);
        }
        return $this->updateVolumeExcess($userId, $coin, $market, $amount);
    }

    public function updateBonusOption($amount, $lot): array
    {
        $excessVolumeOfSecondUser = $this->getExcessVolume($this->trade->seller_id, $this->trade->coin, $this->trade->currency);
        if (BigNumber::new($amount)->add($excessVolumeOfSecondUser)->comp($lot) >= 0) {
            return $this->bonusOption = [2,1];
        }
        return $this->bonusOption = [1,0];
    }

    public function getExcessVolume($userId, $coin, $market)
    {
        $record = TradeVolumeStatistic::where('user_id', $userId)
            ->where('coin', $coin)
            ->where('market', $market)
            ->first();
        if ($record) {
            return $record->volume_excess;
        }
        return;
    }

    /**
     * If $amount + current excess bigger than $lot, pay bonus for user
     *
     * @return boolean
     */
    public function payBonus($userId, $amount, $excessVolume, $coin, $market, $setting, $transactionId, $userSecond)
    {
        $user = User::find($userId);
        $total = BigNumber::new($amount)->add($excessVolume);
        $timesOfBonus = BigNumber::round(BigNumber::new($total)->div($setting->lot), BigNumber::ROUND_MODE_FLOOR, 0);
        $data = $this->getData($user, $timesOfBonus, $setting, $coin, $market, $userSecond);

        //If admin choose Dividend Wallet, needing lock record
        if ($setting->payfor == Consts::TYPE_AIRDROP_BALANCE || $setting->payfor == Consts::TYPE_DIVIDEND_BONUS_BALANCE) {
            $this->createLockRecord($data, $setting->payfor);
        }

        $settings = [
            'timesOfBonus' => $timesOfBonus,
            'data' => $data,
            'amount' => [
                'totalAmount' => $total,
                'excessVolume' => $excessVolume
            ],
            'setting' => $setting,
            'userSecond' => $userSecond,
        ];

        $this->createHistoryRecord($data, $coin, $market, $transactionId, $setting->payfor, $settings);

        //Send bonus coin to user
        SendBonusByAdminJob::dispatch($data, $setting->payfor, $transactionId)->onQueue(Consts::QUEUE_AIRDROP);

        //Update Volume Excess
        $excessVolumeUpdate  = BigNumber::new($amount)->sub(BigNumber::new($timesOfBonus)->mul($setting->lot));
        $this->updateVolumeExcess($userId, $coin, $market, $excessVolumeUpdate);
        return true;
    }

    /**
     * Create history record for bonus
     *
     * @return void
     */
    public function createHistoryRecord($data, $currency, $market, $transactionId, $payFor, $settings)
    {
        $dividend_settings = json_encode($settings);
        logger('========== history records');
        AutoDividendHistory::create([
            'user_id' => $data['user_id'],
            'email' => $data['email'],
            'currency' => $currency,
            'market' => $market,
            'transaction_id' => $transactionId,
            'bonus_currency' => $data['currency'],
            'bonus_amount' => $data['amount'],
            'bonus_wallet' => $payFor,
            'bonus_date' => Carbon::now()->toDateString(),
            'type' => Consts::TYPE_EXCHANGE_BALANCE,
            'status' => Consts::TRANSACTION_STATUS_PENDING,
            'dividend_settings' => $dividend_settings
        ]);
    }

    /**
     * Create lock record for 2 types Dividend as User and Admin
     *
     * @return void
     */
    public function createLockRecord($data, $payfor)
    {
        if ($payfor == Consts::TYPE_AIRDROP_BALANCE) {
            // $enableTypeSpecial = config('airdrop.enable_special_type_unlock');
            // if($enableTypeSpecial) {
                // return $this->createHistoryLockAirdropRecord($data, 1, 0);
            // }
            return $this->createHistoryLockAirdropRecord($data, 0, 0);
        }
        return $this->createHistoryLockAirdropRecord($data, 0, 1);
    }

    public function createHistoryLockAirdropRecord($bonus, $enableTypeSpecial, $dividendBonus)
    {
        $bonus = (object) $bonus;
        $data = [
            'user_id' => $bonus->user_id,
            'email' => $bonus->email,
            'status' => Consts::AIRDROP_UNLOCKING,
            'total_balance' => $bonus->amount,
            'amount' => 0,
            'unlocked_balance' => 0,
            'last_unlocked_date' => $bonus->last_unlocked_date
        ];
        if ($enableTypeSpecial) {
            $data['type'] = Consts::AIRDROP_TYPE_SPECIAL;
        }
        if ($dividendBonus) {
            $data['type'] = Consts::AIRDROP_TYPE_ADMIN;
        }
        return AirdropHistoryLockBalance::create($data);
    }

    public function getData($user, $timesOfBonus, $setting, $coin, $currency, $userSecond)
    {
        $currencyTotalBonus = TotalBonusEachPair::where('currency', $currency)
            ->where('coin', $coin)
            ->where('payout_coin', $setting->payout_coin)
            ->first();
        $amount = BigNumber::new($timesOfBonus)->mul($setting->payout_amount)->toString();
        $totalNeedCompare = $userSecond == 0 ? BigNumber::new($amount)->mul(2)->toString() : $amount;
        if (BigNumber::new($totalNeedCompare)->add($this->totalPaid)->comp($setting->max_bonus) > 0) {
            $amount = BigNumber::new($setting->max_bonus)->sub($this->totalPaid)->div($this->bonusOption[$userSecond]);
        }
        $data = ([
            'user_id' => $user->id,
            'email' => $user->email,
            'currency' => strtolower($setting->payout_coin),
            'status' => Consts::AIRDROP_UNPAID,
            'amount' => $amount,
            'trading_coin' => $coin,
            'trading_currency' => $currency,
            'last_unlocked_date' => Carbon::now()->toDateString(),
            'currencyTotalBonus' => $currencyTotalBonus,
        ]);
        $this->totalPaid = BigNumber::new($this->totalPaid)->add($amount)->toString();
        return $data;
    }

    public function checkTimeCondition($setting): bool
    {
        if (!$setting->enable) {
            return false;
        }
        $conditionBefore = Carbon::today()->diffInDays($setting->time_from, false);
        $conditionAfter = Carbon::today()->diffInDays($setting->time_to, false);
        if ($conditionBefore > 0 || $conditionAfter < 0) {
            return false;
        }
        return true;
    }

    public function getSetting($coin, $market)
    {
        return AutoDividendSetting::where('coin', $coin)
            ->where('market', $market)
            ->first();
    }

    public function updateVolumeExcess($userId, $coin, $market, $volumeExcess)
    {
        $record = TradeVolumeStatistic::where('user_id', $userId)
            ->where('coin', $coin)
            ->where('market', $market)
            ->first();
        if (!$record) {
            $email = User::find($userId)->email;
            return TradeVolumeStatistic::create([
                'user_id' => $userId,
                'email' => $email,
                'coin' => $coin,
                'market' => $market,
                'volume_excess' => $volumeExcess
            ]);
        }

        return TradeVolumeStatistic::where('user_id', $userId)
            ->where('coin', $coin)
            ->where('market', $market)
            ->update([
                'volume_excess' => BigNumber::new($record->volume_excess)->add($volumeExcess)
            ]);
    }

    protected function getProcessKey(): string
    {
        return 'update_trading_volume';
    }

    public function isSelfTrading($buyer_id, $seller_id)
    {
        $settings = Settings::where('key', 'self_trading_auto_dividend_spot')->first();
        if (!$settings) {
            return true;
        }
        if (($buyer_id == $seller_id) && (!$this->settingSelfTrading)) {
            return false;
        }
        return true;
    }
}
