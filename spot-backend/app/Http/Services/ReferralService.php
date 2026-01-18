<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\AffiliateTrees;
use App\Models\CalculateProfit;
use App\Models\CommissionWithdrawal;
use App\Models\CommissionBalance;
use App\Models\ClientReferrerHistory;
use App\Models\MultiReferrerDetails;
use App\Models\ReferrerClientLevel;
use App\Models\ReferrerHistory;
use App\Models\ReferrerRecentActivitiesLog;
use App\Models\ReferrerSetting;
use App\Models\ReportTransaction;
use App\Models\User;
use App\Models\UserRates;
use App\Models\UserSamsubKyc;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use DB;
use Exception;

class ReferralService
{
    public function changeStatus($status): bool
    {
        $changeStatus = ReferrerSetting::first();
        if (!$changeStatus) {
            $changeStatus = $this->createRecords($status);
        }
        if ($changeStatus->enable != $status) {
            $changeStatus->enable = $status;
            $changeStatus->save();
            cache(['referral:setting:current' => $changeStatus], config('referrer.referrer_setting_live_time_cache'));
        }
        return true;
    }

    public function createRecords($status)
    {
        $setting = ReferrerSetting::create([
            'enable' => $status,
            'number_of_levels' => 0,
            'refund_rate' => 0
        ]);
        cache(['referral:setting:current' => $setting], config('referrer.referrer_setting_live_time_cache'));
        return $setting;
    }

    public function getReferralSettings()
    {
        return cache()->remember('referral:setting:current', config('referrer.referrer_setting_live_time_cache'), function () {
            return ReferrerSetting::first();
        });
    }

    public function updateReferralSettings($params)
    {
        $setting = ReferrerSetting::first()->fill($params);
        $setting->save();
        cache(['referral:setting:current' => $setting], config('referrer.referrer_setting_live_time_cache'));
        return $setting;
    }

    public function getReferralHistory($params)
    {
        $searchKey = 'search_key';
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $res = ReferrerHistory::when(!empty($params[$searchKey]), function ($q) use ($params, $searchKey) {
            return $q->where('email', 'like', '%' . $params[$searchKey] . '%');
        })
        ->when(!empty($params['currency']), function ($query) use ($params) {
            return $query->where('coin', $params['currency']);
        })
        ->when(!empty($params['type']), function ($query) use ($params) {
            return $query->where('type', $params['type']);
        })
        ->when(!empty($params['start_date']), function ($query) use ($params) {
            $startDate = Carbon::createFromTimestamp($params['start_date']);
            return $query->where('created_at', '>=', $startDate);
        })
        ->when(!empty($params['end_date']), function ($query) use ($params) {
            $endDate = Carbon::createFromTimestamp($params['end_date']);
            return $query->where('created_at', '<', $endDate);
        })
        ->when(
            !empty($params['sort']) && !empty($params['sort_type']),
            function ($query) use ($params) {
                return $query->orderBy($params['sort'], $params['sort_type']);
            },
            function ($query) use ($params) {
                return $query->orderBy('created_at', 'desc');
            }
        )
        ->paginate($limit);
        return $res;
    }

    public function getTotalReferrer($userId)
    {
        $setting = $this->getReferralSettings();
        $totalLevel = $setting->number_of_levels;
        $user = MultiReferrerDetails::where('user_id', $userId)->first();
        $getTotalReferralAt = 'number_of_referrer_lv_' . $totalLevel;

        return $user->$getTotalReferralAt;
    }

    public function checkCommissionRate($user, $commissionRate) {
        $upId = $user->referrer_id;
        $level1DownIds = AffiliateTrees::where('referrer_id', $user->id)
            ->where('level', 1)
            ->pluck('user_id')
            ->all();

        $rates = UserRates::whereIn('id', array_merge([$upId], $level1DownIds))->get();
        $upRate = $rates->where('id', $upId)->first()->commission_rate ?? -1;
        $downRate = $rates->whereIn('id', $level1DownIds)->max('commission_rate') ?? -1;

        if($upRate >= 0 && $commissionRate > $upRate) {
            return [
                'success' => false,
                'message' => "Account must have a maximum commission rate of {$upRate}%",
            ];
        } 
        if($downRate >= 0 && $commissionRate < $downRate) {
            return [
                'success' => false,
                'message' => "Account must have a minimum commission rate of {$downRate}%",
            ];
        }

        return ['success' => true];
    }
    
    public function getRangeSettingCommissionById($user) {
        $upId = $user->referrer_id;
        $level1DownIds = AffiliateTrees::where('referrer_id', $user->id)
            ->where('level', 1)
            ->pluck('user_id')
            ->all();

        $rates = UserRates::whereIn('id', array_merge([$upId], $level1DownIds))->get();
        $upRate = $rates->where('id', $upId)->first()->commission_rate ?? '100';
        $downRate = $rates->whereIn('id', $level1DownIds)->max('commission_rate') ?? '0';

        return [
            'minimumCommission' => $downRate,
            'maximumCommission' => $upRate,
        ];
    }

    public function addDirectCommission($transaction)
    {
        $user = $transaction->user;
        if(!$user) {
            //case future
            throw new Exception('user_id not found');
        }
        $userIdNeedRefund = $user->referrer_id;
        $referralFee = 0;

        $coin = $transaction->transaction_type == Consts::ORDER_SIDE_BUY ? $transaction->coin : $transaction->currency;
        $commissionPercent = $user->referrerUser->userRate->commission_rate ?? 0;
        if ($userIdNeedRefund && !is_null($user->referrerUser->is_partner) && $commissionPercent != 0 && $transaction->fee > 0) {
            
            $amount = BigNumber::new($transaction->fee)->mul($commissionPercent)->div(100);
            $usdtValue = BigNumber::new($transaction->fee_usdt)->mul($commissionPercent)->div(100);
            $referralFee = $amount->toString();

            if($transaction->type == Consts::TYPE_EXCHANGE_BALANCE) {
                $this->refundAndCreateHistoryRecord(
                    $userIdNeedRefund,
                    $user->referrerUser->email,
                    $amount,
                    BigNumber::new($commissionPercent),
                    strtoupper($coin),
                    $transaction->id,
                    $transaction->order_id,
                    $user, 
                    $usdtValue,
                    $transaction->executed_date,
                    1
                );
            } else {
                $message = [
                    "userId" => $userIdNeedRefund,
                    "amount" => $amount->toString(),
                    "asset" => $coin,
                    "type" => "REFERRAL"
                ];
                $topic = Consts::TOPIC_PRODUCER_FUTURE_REFERRAL;
                Utils::kafkaProducer($topic, $message);
                $this->refundAndCreateFutureHistoryRecord(
                    $userIdNeedRefund,
                    $user->referrerUser->email,
                    $amount,
                    BigNumber::new($commissionPercent),
                    strtoupper($coin),
                    $transaction->id,
					$transaction->order_id,
                    $user,
                    $usdtValue,
                    $transaction->symbol_future,
                    $coin,
                    $transaction->executed_date,
                    1
                );
            }

            $this->createCommissionReportTransaction($userIdNeedRefund, $usdtValue, 1);
            $this->createTotalFeeDirectReferral($referralFee, $coin);
        }
    }

    public function createVolumeReportTransaction($user_id, $volume, $fee) {
        $today = Carbon::now()->toDateString();

        $columnsToUpdate = [
            'volume' => DB::raw('volume + ' . $volume),
            'fee' => DB::raw('fee + ' . $fee)
        ];

        ReportTransaction::upsert([
            [
                'date' => $today,
                'user_id' => $user_id,
                'volume' => $volume,
                'fee' => $fee
            ]
        ], ['date', 'user_id'], $columnsToUpdate);
    }

    public function createCommissionReportTransaction($user_id, $commission, $isDirectRef, $isPartner = 0) {
        $today = Carbon::now()->toDateString();
        $hour = Carbon::now()->hour;
        if ($isPartner || $hour == 0) {
            $today = Carbon::yesterday()->toDateString();
        }

        $directCommission = $isDirectRef ? $commission : 0;
        $indirectCommission = $isDirectRef ? 0 : $commission;

        $columnsToUpdate = [
            'commission' => DB::raw('commission + ' . $commission),
            'direct_commission' => DB::raw('direct_commission + ' . $directCommission),
            'indirect_commission' => DB::raw('indirect_commission + ' . $indirectCommission),
        ];

        ReportTransaction::upsert([
            [
                'date' => $today,
                'user_id' => $user_id,
                'commission' => $commission,
                'direct_commission' => $directCommission,
                'indirect_commission' => $indirectCommission,
            ]
        ], ['date', 'user_id'], $columnsToUpdate);
    }

    public function addPartnerCommission($transaction)
    {
        $user = $transaction->user;
        if(!$user) {
            //case future
            throw new Exception('user_id not found');
        }

        if ($transaction->fee <= 0) {return;}
        $referralFee = 0;
        $partnerTreeExceptLevel1 = $user->affiliateTreeUsers->where('level', '<>', 1)->all();//level1 is direct user
        
        $rateByLevel = DB::table('affiliate_trees AS a')
            ->join('user_rates AS b', 'a.referrer_id', 'b.id')
            ->where('a.user_id', $user->id)
            ->pluck('commission_rate', 'level')
            ->all();

        $coin = $transaction->transaction_type == Consts::ORDER_SIDE_BUY ? $transaction->coin : $transaction->currency;
        foreach($partnerTreeExceptLevel1 as $partner) {
            $userNeedRefund = User::find($partner['referrer_id']);

            $currentRate = $rateByLevel[$partner['level']] ?? 0;
            $downRate = $rateByLevel[$partner['level'] - 1] ?? 0;
            if (is_null($userNeedRefund->is_partner) || $currentRate == 0) {continue;}
            $commissionPercent = $currentRate - $downRate;

            $amount = BigNumber::new($transaction->fee)->mul($commissionPercent)->div(100);
            $usdtValue = BigNumber::new($transaction->fee_usdt)->mul($commissionPercent)->div(100);
    
            $referralFee = BigNumber::new($referralFee)->add($amount)->toString();

            if($transaction->type == Consts::TYPE_EXCHANGE_BALANCE) {
                $this->refundAndCreateHistoryRecord(
                    $userNeedRefund->id,
                    $userNeedRefund->email,
                    $amount,
                    BigNumber::new($commissionPercent),
                    strtoupper($coin),
                    $transaction->id,
					$transaction->order_id,
                    $user,
                    $usdtValue,
                    $transaction->executed_date
                );
            } else {
                $message = [
                    "userId" => $userNeedRefund->id,
                    "amount" => $amount->toString(),
                    "asset" => $coin,
                    "type" => "REFERRAL"
                ];
                $topic = Consts::TOPIC_PRODUCER_FUTURE_REFERRAL;
                Utils::kafkaProducer($topic, $message);
                $this->refundAndCreateFutureHistoryRecord(
                    $userNeedRefund->id,
                    $userNeedRefund->email,
                    $amount,
                    BigNumber::new($commissionPercent),
                    strtoupper($coin),
                    $transaction->id,
					$transaction->order_id,
                    $user,
                    $usdtValue,
                    $transaction->symbol_future,
                    $coin,
                    $transaction->executed_date,
                    0
                );
            }

            $this->createCommissionReportTransaction($userNeedRefund->id, $usdtValue, 0, 1);
        }
        
        $this->createTotalFeePartnerReferral($referralFee, $coin);
    }

    public function refundAndCreateHistoryRecord($userId, $email, $amount, $commissionRate, $coin, $transactionId, $orderId,
        $transactionOwner, $usdtValue, $executedDate, $isDirectRef = 0)
    {
        DB::beginTransaction();
        try {
            $this->updateBalanceCoin($coin, $userId, $amount);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger()->error($e);
        } finally {
            $data = [
                'user_id' => $userId,
                'email' => $email,
                'amount' => $amount,
                'coin' => $coin,
                'commission_rate' => $commissionRate,
                'complete_transaction_id' => $transactionId,
				'order_transaction_id' => $orderId,
                'transaction_owner' => $transactionOwner->id,
                'transaction_owner_email' => $transactionOwner->email,
                'type' => Consts::TYPE_EXCHANGE_BALANCE,
                'is_direct_ref' => $isDirectRef,
                'usdt_value' => $usdtValue,
                'executed_date' => $executedDate
            ];
            ReferrerHistory::create($data);
        }
    }

    public function createCalculateProfit($fee, $coin) {
        $today = Carbon::now()->toDateString();

        $hasRecord = CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->first();
        if (!$hasRecord) {
            return CalculateProfit::create([
                'date' => $today,
                'coin' => $coin,
                'receive_fee' => $fee,
                'referral_fee' => 0,
                'net_fee' => $fee
            ]);
        }
        $record = CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->lockForUpdate()
            ->first();
        return CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->update([
                'receive_fee' => BigNumber::new($record->receive_fee)->add($fee),
                'net_fee' => BigNumber::new($record->receive_fee)->add($fee)
            ]);
    }

    public function createTotalFeeDirectReferral($referralFee, $coin)
    {
        $today = Carbon::now()->toDateString();
        $hour = Carbon::now()->hour;
        if ($hour == 0) {$today = Carbon::yesterday()->toDateString();}

        $hasRecord = CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->first();
        if (!$hasRecord) {
            return CalculateProfit::create([
                'date' => $today,
                'coin' => $coin,
                'receive_fee' => 0,
                'referral_fee' => $referralFee,
                'net_fee' => BigNumber::new(0)->sub($referralFee)
            ]);
        }
        $record = CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->lockForUpdate()
            ->first();
        return CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->update([
                'referral_fee' => BigNumber::new($record->referral_fee)->add($referralFee),
                'net_fee' => BigNumber::new($record->receive_fee)->sub(BigNumber::new($record->referral_fee))
                    ->sub($referralFee)->sub(BigNumber::new($record->client_referral_fee))
            ]);
    }
    
    public function createTotalFeePartnerReferral($referralFee, $coin)
    {
        $today = Carbon::yesterday()->toDateString();
        $hasRecord = CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->first();
        if (!$hasRecord) {
            return CalculateProfit::create([
                'date' => $today,
                'coin' => $coin,
                'receive_fee' => 0,
                'referral_fee' => $referralFee,
                'net_fee' => BigNumber::new(0)->sub($referralFee)
            ]);
        }
        $record = CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->lockForUpdate()
            ->first();
        return CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->update([
                'referral_fee' => BigNumber::new($record->referral_fee)->add($referralFee),
                'net_fee' => BigNumber::new($record->receive_fee)->sub(BigNumber::new($record->referral_fee))
                    ->sub($referralFee)->sub(BigNumber::new($record->client_referral_fee))
            ]);
    }

    public function createTotalFeeClientReferral($referralFee, $coin)
    {
        $today = Carbon::now()->toDateString();
        $hour = Carbon::now()->hour; 
        if ($hour == 0) {$today = Carbon::yesterday()->toDateString();}
        $hasRecord = CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->first();
        if (!$hasRecord) {
            return CalculateProfit::create([
                'date' => $today,
                'coin' => $coin,
                'receive_fee' => 0,
                'referral_fee' => 0,
                'client_referral_fee' => $referralFee,
                'net_fee' => BigNumber::new(0)->sub($referralFee)
            ]);
        }
        $record = CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->lockForUpdate()
            ->first();
            
        return CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->update([
                'client_referral_fee' => BigNumber::new($record->client_referral_fee)->add($referralFee),
                'net_fee' => BigNumber::new($record->receive_fee)->sub(BigNumber::new($record->referral_fee))
                    ->sub(BigNumber::new($record->client_referral_fee))->sub($referralFee)
            ]);
    }

    public function updateBalanceCoin($coin, $userId, $amount)
    {
        $table = 'spot_' . strtolower($coin) . '_accounts';
        $balanceRecord = $this->getBalance($table, $userId);
        $availableBalance = $balanceRecord->available_balance;
        $balance = $balanceRecord->balance;
        return DB::table($table)
            ->where('id', $userId)
            ->update([
                'balance' => BigNumber::new($balance)->add($amount),
                'available_balance' => BigNumber::new($availableBalance)->add($amount),
                'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * Update balance coin with proper locking for withdrawal operations
     * 
     * @param string $coin
     * @param int $userId
     * @param float $amount
     * @return int
     */
    public function updateBalanceCoinWithLock($coin, $userId, $amount)
    {
        $table = 'spot_' . strtolower($coin) . '_accounts';
        
        // Use lockForUpdate to prevent concurrent balance modifications
        $balanceRecord = DB::connection('master')->table($table)
            ->where('id', $userId)
            ->lockForUpdate()
            ->first();
            
        if (!$balanceRecord) {
            throw new Exception("Balance record not found for user {$userId} and coin {$coin}");
        }
        
        $availableBalance = $balanceRecord->available_balance;
        $balance = $balanceRecord->balance;
        
        return DB::table($table)
            ->where('id', $userId)
            ->update([
                'balance' => BigNumber::new($balance)->add($amount),
                'available_balance' => BigNumber::new($availableBalance)->add($amount),
                'updated_at' => Carbon::now(),
            ]);
    }

    public function getBalance($table, $userId)
    {
        return DB::connection('master')->table($table)
            ->where('id', $userId)
            ->lockForUpdate()
            ->first();
    }

    public function refundAndCreateFutureHistoryRecord($userId, $email, $amount, $commissionRate, $coin, $transactionId, $orderId, $transactionOwner,
        $usdtValue, $symbol, $assetFuture, $executedDate, $isDirectRef = 0)
    {
        $data = [
            'user_id' => $userId,
            'email' => $email,
            'amount' => $amount,
            'coin' => $coin,
            'commission_rate' => $commissionRate,
            'complete_transaction_id' => $transactionId,
			'order_transaction_id' => $orderId,
            'transaction_owner' => $transactionOwner->id,
            'transaction_owner_email' => $transactionOwner->email,
            'type' => Consts::TYPE_FUTURE_BALANCE,
            'symbol' => $symbol,
            'asset_future' => $assetFuture,
            'usdt_value' => $usdtValue,
            'is_direct_ref' => $isDirectRef,
            'executed_date' => $executedDate
        ];
        ReferrerHistory::create($data);
    }

    /**
     * Withdraw commission
     *
     * @param int $userId
     * @param float $amount
     * @return array
     */
    public function withdrawCommission($userId, $amount)
    {
        // Validate amount
        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid withdrawal amount'
            ];
        }

        DB::beginTransaction();
        try {
            // Use lockForUpdate to prevent concurrent modifications of the commission balance
            $commissionBalance = CommissionBalance::where('id', $userId)
                ->lockForUpdate()
                ->first();

            // Check if user has commission balance record
            if (!$commissionBalance) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Commission balance not found'
                ];
            }

            // Check if user has enough withdrawable commission
            if ($commissionBalance->available_balance < $amount) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Insufficient withdrawable commission'
                ];
            }

            // Create withdrawal record
            $withdrawal = CommissionWithdrawal::create([
                'user_id' => $userId,
                'amount' => $amount,
                'status' => 'completed'
            ]);

            // Update commission balance with proper locking (already locked above)
            $commissionBalance->available_balance -= $amount;
            $commissionBalance->withdrawn_balance += $amount;
            $commissionBalance->save();

            // Add USDT to user's spot account with proper locking
            $this->updateBalanceCoinWithLock('USDT', $userId, $amount);

            DB::commit();

            return [
                'success' => true,
                'data' => $withdrawal
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Commission withdrawal failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'amount' => $amount,
                'error' => $e
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process withdrawal'
            ];
        }
    }

    /**
     * Get commission withdrawal history
     *
     * @param int $userId
     * @param array $params
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getCommissionWithdrawalHistory($userId, $params = [])
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        return CommissionWithdrawal::where('user_id', $userId)
            ->when(!empty($params['start_date']), function($query) use ($params) {
                return $query->where('created_at', '>=', Carbon::parse($params['start_date']));
            })
            ->when(!empty($params['end_date']), function($query) use ($params) {
                return $query->where('created_at', '<=', Carbon::parse($params['end_date'])->endOfDay());
            })
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Get commission overview including total, monthly and withdrawable amounts
     * 
     * @param int $userId
     * @param array $params
     * @return array
     */
    public function getCommissionOverview($userId, $params = [])
    {
        // Get current commission balance
        $currentBalance = CommissionBalance::where('id', $userId)->first();

        // Get total commission at exactly 30 days ago
        $previousPeriodData = ReportTransaction::where('user_id', $userId)
            ->where('date', '<=', Carbon::now()->subDays(30)->startOfDay())
            ->select([
                DB::raw('SUM(CAST(referral_commission as DECIMAL(20,8))) as total_commission')
            ])
            ->first();

        // Calculate total commission change
        $currentTotal = $currentBalance ? $currentBalance->balance : 0;
        $previousTotal = $previousPeriodData->total_commission ?? 0;
        $totalChange = $previousTotal > 0 
            ? (($currentTotal - $previousTotal) / $previousTotal * 100)
            : 0;

        // Get date range
        $startDate = isset($params['start_date']) ? Carbon::parse($params['start_date']) : Carbon::now()->startOfMonth();
        $endDate = isset($params['end_date']) ? Carbon::parse($params['end_date'])->endOfDay() : Carbon::now()->endOfMonth();

        // Initialize change values
        $monthlyChange = 0;
        $withdrawableChange = 0;

        // Only calculate percentage changes if no date inputs are provided
        if (!isset($params['start_date']) && !isset($params['end_date'])) {
            // Get current month's commission
            $currentMonth = Carbon::now();
            $currentPeriodCommission = ReportTransaction::where('user_id', $userId)
                ->whereMonth('date', $currentMonth->month)
                ->whereYear('date', $currentMonth->year)
                ->sum(DB::raw('CAST(referral_commission as DECIMAL(20,8))'));

            // Get previous month's commission
            $previousMonth = Carbon::now()->subMonthNoOverflow();
            $previousPeriodCommission = ReportTransaction::where('user_id', $userId)
                ->whereMonth('date', $previousMonth->month)
                ->whereYear('date', $previousMonth->year)
                ->sum(DB::raw('CAST(referral_commission as DECIMAL(20,8))'));

            // Calculate monthly commission change
            $monthlyChange = $previousPeriodCommission > 0
                ? (($currentPeriodCommission - $previousPeriodCommission) / $previousPeriodCommission * 100)
                : 0;

            // Get withdrawable amount
            $withdrawableAmount = $currentBalance ? $currentBalance->available_balance : 0;
            $previousWithdrawableAmount = $previousPeriodData->total_commission ?? 0;

            // Calculate withdrawable amount change
            $withdrawableChange = $previousWithdrawableAmount > 0
                ? (($withdrawableAmount - $previousWithdrawableAmount) / $previousWithdrawableAmount * 100)
                : 0;
        } else {
            // Get current period's commission
            $currentPeriodCommission = ReportTransaction::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->sum(DB::raw('CAST(referral_commission as DECIMAL(20,8))'));

            // Calculate withdrawable amount for the period
            $withdrawableAmount = ReportTransaction::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->sum(DB::raw('CAST(referral_commission as DECIMAL(20,8))'));
        }

        return [
            'total_commission' => [
                'value' => number_format($currentTotal, 8),
                'change' => round($totalChange, 2)
            ],
            'monthly_commission' => [
                'value' => number_format($currentPeriodCommission, 8),
                'change' => round($monthlyChange, 2)
            ],
            'withdrawable_amount' => [
                'value' => number_format($withdrawableAmount, 8),
                'change' => round($withdrawableChange, 2)
            ]
        ];
    }

    /**
     * Get commission trends with daily data
     * 
     * @param int $userId
     * @param array $params
     * @return array
     */
    public function getCommissionDailyTrends($userId, $params = [])
    {
        $dailyPeriod = Arr::get($params, 'daily_period', '');
        
        // Determine start date for daily trend
        $dailyEndDate = Carbon::now();
        switch ($dailyPeriod) {
            case '30d':
                $dailyStartDate = Carbon::now()->subDays(30)->startOfDay();
                break;
            case '90d':
                $dailyStartDate = Carbon::now()->subDays(90)->startOfDay();
                break;
            default:
                $dailyStartDate = isset($params['daily_start_date']) ? Carbon::parse($params['daily_start_date']) : Carbon::now()->subDays(7)->startOfDay();
                $dailyEndDate = isset($params['daily_end_date']) ? Carbon::parse($params['daily_end_date'])->endOfDay() : Carbon::now();
        }

        // Get daily commission data
        $dailyData = ReportTransaction::where('user_id', $userId)
            ->whereBetween('date', [$dailyStartDate, $dailyEndDate])
            ->select([
                DB::raw('DATE(date) as date'),
                DB::raw('SUM(CAST(referral_commission as DECIMAL(20,8))) as daily_commission')
            ])
            ->groupBy(DB::raw('DATE(date)'))
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'commission' => number_format($item->daily_commission, 8)
                ];
            });

        // Create array of all days in the period
        $allDays = [];
        $currentDay = Carbon::parse($dailyStartDate);
        $endDay = Carbon::parse($dailyEndDate);

        while ($currentDay <= $endDay) {
            $dayKey = $currentDay->format('Y-m-d');
            $allDays[$dayKey] = [
                'date' => $dayKey,
                'commission' => '0.00000000'
            ];
            $currentDay->addDay();
        }

        // Merge actual data with all days
        foreach ($dailyData as $data) {
            $allDays[$data['date']] = $data;
        }

        // Convert to array and sort by date
        $dailyData = array_values($allDays);
        usort($dailyData, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return [
            'data' => $dailyData,
            'period' => $dailyPeriod,
            'start_date' => $dailyStartDate->toDateString(),
            'end_date' => $dailyEndDate->toDateString()
        ];
    }

    /**
     * Get commission trends with monthly data
     *
     * @param int $userId
     * @param array $params
     * @return array
     */
    public function getCommissionMonthlyTrends($userId, $params = [])
    {
        $monthlyPeriod = Arr::get($params, 'monthly_period', '');

        // Determine start date for monthly trend
        $monthlyEndDate = Carbon::now();
        switch ($monthlyPeriod) {
            case '3m':
                $monthlyStartDate = Carbon::now()->subMonths(3);
                break;
            case '6m':
                $monthlyStartDate = Carbon::now()->subMonths(6);
                break;
            case '1y':
                $monthlyStartDate = Carbon::now()->subYear();
                break;
            default:
                $monthlyStartDate = isset($params['monthly_start_date']) ? Carbon::parse($params['monthly_start_date']) : Carbon::now()->startOfYear();
                $monthlyEndDate = isset($params['monthly_end_date']) ? Carbon::parse($params['monthly_end_date'])->endOfMonth() : Carbon::now();
        }

        // Get monthly commission data
        $monthlyData = ReportTransaction::where('user_id', $userId)
            ->whereBetween('date', [$monthlyStartDate, $monthlyEndDate])
            ->select([
                DB::raw('DATE_FORMAT(date, "%Y-%m") as month'),
                DB::raw('SUM(CAST(referral_commission as DECIMAL(20,8))) as monthly_commission')
            ])
            ->groupBy(DB::raw('DATE_FORMAT(date, "%Y-%m")'))
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'commission' => number_format($item->monthly_commission, 8)
                ];
            });

        // Create array of all months in the period
        $allMonths = [];
        $currentMonth = Carbon::parse($monthlyStartDate);
        $endMonth = Carbon::parse($monthlyEndDate);

        while ($currentMonth <= $endMonth) {
            $monthKey = $currentMonth->format('Y-m');
            $allMonths[$monthKey] = [
                'month' => $monthKey,
                'commission' => '0.00000000'
            ];
            $currentMonth->addMonth();
        }

        // Merge actual data with all months
        foreach ($monthlyData as $data) {
            $allMonths[$data['month']] = $data;
        }

        // Convert to array and sort by month
        $monthlyData = array_values($allMonths);
        usort($monthlyData, function($a, $b) {
            return strcmp($a['month'], $b['month']);
        });

        return [
            'data' => $monthlyData,
            'period' => $monthlyPeriod,
            'start_date' => $monthlyStartDate->toDateString(),
            'end_date' => $monthlyEndDate->toDateString()
        ];
    }

    /**
     * Get commission history with detailed information
     * 
     * @param int $userId
     * @param array $params
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getCommissionHistory($userId, $params = [])
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $searchKey = Arr::get($params, 'search_key');
        $startDate = Arr::get($params, 'start_date');
        $endDate = Arr::get($params, 'end_date');
        $status = Arr::get($params, 'status');
        $sortBy = Arr::get($params, 'sort_by', 'created_at');
        $sortType = Arr::get($params, 'sort_type', 'desc');

        $query = DB::table('complete_transactions as ct')
            ->join('affiliate_trees as at', function ($join) {
                $join->on('ct.user_id', '=', 'at.user_id')
                     ->where('at.level', '=', 1);
            })
            ->join('users as u', 'ct.user_id', '=', 'u.id')
            ->leftJoin('client_referrer_histories as crh', 'ct.id', '=', 'crh.complete_transaction_id')
            ->where('at.referrer_id', $userId)
            ->select([
                'ct.id',
                'ct.created_at',
                'ct.amount_usdt as trading_volume',
                'ct.type as transaction_type',
                'ct.is_calculated_client_ref',
                'u.id as referral_id',
                'u.uid',
                'u.email',
                DB::raw('COALESCE(crh.usdt_value, 0) as commission')
            ]);

        // Apply filters
        if ($searchKey) {
            $query->where(function($q) use ($searchKey) {
                $q->where('u.uid', 'like', "%{$searchKey}%")
                  ->orWhere('u.email', 'like', "%{$searchKey}%");
            });
        }

        if ($startDate) {
            $query->whereDate('ct.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('ct.created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        if ($status) {
            match ($status) {
                'completed' => $query->where('ct.is_calculated_client_ref', Consts::IS_CALCULATED_CLIENT_REF_COMPLETED),
                'processing' => $query->where('ct.is_calculated_client_ref', Consts::IS_CALCULATED_CLIENT_REF_PROCESSING),
                'failed' => $query->where('ct.is_calculated_client_ref', Consts::IS_CALCULATED_CLIENT_REF_FAIL),
                default => null
            };
        }

        // Apply sorting
        $query->orderBy('ct.' . $sortBy, $sortType);

        $histories = $query->paginate($limit);

        // Transform the result to include only required fields
        $histories->getCollection()->transform(function ($history) {
            $status = match ($history->is_calculated_client_ref) {
                Consts::IS_CALCULATED_CLIENT_REF_PROCESSING => 'processing',
                Consts::IS_CALCULATED_CLIENT_REF_COMPLETED => 'completed',
                Consts::IS_CALCULATED_CLIENT_REF_FAIL => 'failed',
                default => 'unknown'
            };

            return [
                'date' => Carbon::parse($history->created_at)->toISOString(),
                'id' => $history->id,
                'referral_id' => $history->referral_id,
                'uid' => $history->uid ?? '',
                'email' => $history->email ?? '',
                'transaction_type' => $history->transaction_type,
                'trading_volume' => number_format($history->trading_volume ?? 0, 8),
                'commission' => number_format($history->commission ?? 0, 8),
                'status' => $status
            ];
        });

        return $histories;
    }

    /**
     * Export commission history to CSV
     * 
     * @param int $userId
     * @param array $params
     * @return array
     */
    public function exportCommissionHistory($userId, $params = [])
    {
        $searchKey = Arr::get($params, 'search_key');
        $startDate = Arr::get($params, 'start_date');
        $endDate = Arr::get($params, 'end_date');
        $status = Arr::get($params, 'status');

        $query = DB::table('complete_transactions as ct')
            ->join('affiliate_trees as at', function ($join) {
                $join->on('ct.user_id', '=', 'at.user_id')
                     ->where('at.level', '=', 1);
            })
            ->join('users as u', 'ct.user_id', '=', 'u.id')
            ->leftJoin('client_referrer_histories as crh', 'ct.id', '=', 'crh.complete_transaction_id')
            ->where('at.referrer_id', $userId)
            ->select([
                'ct.id',
                'ct.created_at',
                'ct.amount_usdt as trading_volume',
                'ct.type as transaction_type',
                'ct.is_calculated_client_ref',
                'u.id as referral_id',
                'u.uid',
                'u.email',
                DB::raw('COALESCE(crh.usdt_value, 0) as commission')
            ]);

        // Apply filters
        if ($searchKey) {
            $query->where(function($q) use ($searchKey) {
                $q->where('u.uid', 'like', "%{$searchKey}%")
                  ->orWhere('u.email', 'like', "%{$searchKey}%");
            });
        }

        if ($startDate) {
            $query->whereDate('ct.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('ct.created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        if ($status) {
            match ($status) {
                'completed' => $query->where('ct.is_calculated_client_ref', Consts::IS_CALCULATED_CLIENT_REF_COMPLETED),
                'processing' => $query->where('ct.is_calculated_client_ref', Consts::IS_CALCULATED_CLIENT_REF_PROCESSING),
                'failed' => $query->where('ct.is_calculated_client_ref', Consts::IS_CALCULATED_CLIENT_REF_FAIL),
                default => null
            };
        }

        $histories = $query->orderBy('ct.created_at', 'desc')->get();

        // Transform data for export
        $rows = [];
        // Add header row
        $rows[] = [
            'Date',
            'UID',
            'Email',
            'Transaction Type',
            'Trading Volume (USDT)',
            'Commission (USDT)',
            'Status'
        ];

        // Add data rows
        foreach ($histories as $history) {
            $status = match ($history->is_calculated_client_ref) {
                Consts::IS_CALCULATED_CLIENT_REF_PROCESSING => 'processing',
                Consts::IS_CALCULATED_CLIENT_REF_COMPLETED => 'completed',
                Consts::IS_CALCULATED_CLIENT_REF_FAIL => 'failed',
                default => 'unknown'
            };

            $rows[] = [
                Carbon::parse($history->created_at)->format('Y-m-d H:i:s'),
                $history->uid ?? '',
                $history->email ?? '',
                $history->transaction_type,
                number_format($history->trading_volume ?? 0, 8),
                number_format($history->commission ?? 0, 8),
                $status
            ];
        }

        return $rows;
    }

    /**
     * Get referral overview including total referrals, active referrals and conversion rate
     *
     * @param int $userId
     * @param array $params
     * @return array
     */
    public function getReferralOverview($userId, $params = [])
    {
        // Get date range
        $startDate = isset($params['start_date']) ? Carbon::parse($params['start_date']) : Carbon::now()->startOfMonth();
        $endDate = isset($params['end_date']) ? Carbon::parse($params['end_date'])->endOfDay() : Carbon::now()->endOfMonth();

        // Initialize change values
        $totalReferralsChange = 0;
        $activeReferralsChange = 0;
        $conversionRateChange = 0;

        // Only calculate percentage changes if no date inputs are provided
        if (!isset($params['start_date']) && !isset($params['end_date'])) {
            $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
            $today = Carbon::now()->endOfDay();

            // Get current month data
            $currentTotalReferrals = AffiliateTrees::where('referrer_id', $userId)
                ->where('level', 1)
                ->count();

            $currentActiveReferrals = User::whereHas('affiliateTreesUp', function ($query) use ($userId) {
                $query->where('referrer_id', $userId)
                    ->where('level', 1);
            })
            ->whereHas('completeTransactions', function ($query) use ($startOfLastMonth, $today) {
                $query->whereBetween('created_at', [$startOfLastMonth, $today]);
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('user_security_settings')
                    ->whereColumn('user_security_settings.id', 'users.id')
                    ->where('user_security_settings.identity_verified', 1);
            })
            ->count();

            // Get previous month data
            $previousTotalReferrals = AffiliateTrees::where('referrer_id', $userId)
                ->where('level', 1)
                ->where('created_at', '>=', Carbon::now()->subMonthNoOverflow()->startOfMonth())
                ->count();

            $previousActiveReferrals = User::whereHas('affiliateTreesUp', function ($query) use ($userId) {
                $query->where('referrer_id', $userId)
                    ->where('level', 1);
            })
            ->whereHas('completeTransactions', function ($query) use ($startOfLastMonth, $today) {
                $query->whereBetween('created_at', [$startOfLastMonth->copy()->subMonthNoOverflow(), $today->copy()->subMonthNoOverflow()]);
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('user_security_settings')
                    ->whereColumn('user_security_settings.id', 'users.id')
                    ->where('user_security_settings.identity_verified', 1);
            })
            ->count();

            // Calculate changes
            $totalReferralsChange = $previousTotalReferrals > 0 
                ? (($currentTotalReferrals - $previousTotalReferrals) / $previousTotalReferrals * 100)
                : 0;

            $activeReferralsChange = $previousActiveReferrals > 0
                ? (($currentActiveReferrals - $previousActiveReferrals) / $previousActiveReferrals * 100)
                : 0;

            // Calculate conversion rates
            $currentConversionRate = $currentTotalReferrals > 0 
                ? ($currentActiveReferrals / $currentTotalReferrals * 100) 
                : 0;

            $previousConversionRate = $previousTotalReferrals > 0
                ? ($previousActiveReferrals / $previousTotalReferrals * 100)
                : 0;

            $conversionRateChange = $previousConversionRate > 0
                ? ($currentConversionRate - $previousConversionRate)
                : 0;
        } else {
            // Get current period data
            $currentTotalReferrals = AffiliateTrees::where('referrer_id', $userId)
                ->where('level', 1)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $currentActiveReferrals = User::whereHas('affiliateTreesUp', function ($query) use ($userId) {
                $query->where('referrer_id', $userId)
                    ->where('level', 1);
            })
            ->whereHas('completeTransactions', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('user_security_settings')
                    ->whereColumn('user_security_settings.id', 'users.id')
                    ->where('user_security_settings.identity_verified', 1);
            })
            ->count();

            // Calculate current conversion rate
            $currentConversionRate = $currentTotalReferrals > 0 
                ? ($currentActiveReferrals / $currentTotalReferrals * 100) 
                : 0;
        }

        return [
            'total_referrals' => [
                'value' => $currentTotalReferrals,
                'change' => round($totalReferralsChange, 2)
            ],
            'active_referrals' => [
                'value' => $currentActiveReferrals,
                'change' => round($activeReferralsChange, 2)
            ],
            'conversion_rate' => [
                'value' => round($currentConversionRate, 2),
                'change' => round($conversionRateChange, 2)
            ]
        ];
    }

    /**
     * Get referral list with detailed information
     *
     * @param int $userId
     * @param array $params
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getReferralList($userId, $params = [])
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $searchKey = Arr::get($params, 'search_key');
        $onlyActive = Arr::get($params, 'only_active', false) == 'true';
        $sortBy = Arr::get($params, 'sort_by', 'created_at');
        $sortType = Arr::get($params, 'sort_type', 'desc');

        $query = User::whereHas('affiliateTreesUp', function ($query) use ($userId) {
            $query->where('referrer_id', $userId)
                ->where('level', 1);
        })
        ->select([
            'id',
            'uid',
            'email',
            'created_at'
        ])
        ->withSum('reportTransactions as trading_volume', 'volume')
        ->addSelect([
            // Get commission that this referral generated for the referrer
            DB::raw("(SELECT COALESCE(SUM(usdt_value), 0) 
                      FROM client_referrer_histories 
                      WHERE user_id = {$userId} 
                      AND transaction_owner = users.id) as referral_commission")
        ]);

        // Apply search filter
        if ($searchKey) {
            $query->where(function ($q) use ($searchKey) {
                $q->where('uid', 'like', '%' . $searchKey . '%')
                  ->orWhere('email', 'like', '%' . $searchKey . '%');
            });
        }

        $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $today = Carbon::now()->endOfDay();

        // Apply active filter (both recent trades and KYC verified)
        if ($onlyActive === true) {
            $query->whereHas('completeTransactions', function ($q) use ($startOfLastMonth, $today) {
                $q->whereBetween('created_at', [$startOfLastMonth, $today]);
            })->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('user_security_settings')
                    ->whereColumn('user_security_settings.id', 'users.id')
                    ->where('user_security_settings.identity_verified', 1);
            });
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortType);

        $referralList = $query->paginate($limit);

        // Transform the result to include status and default values
        $referralList->getCollection()->transform(function ($referral) use ($startOfLastMonth, $today) {
            // Check both trading activity and KYC status for Active status
            $hasRecentTrades = $referral->completeTransactions()
                ->whereBetween('created_at', [$startOfLastMonth, $today])
                ->exists();

            $isKycVerified = DB::table('user_security_settings')
                ->where('id', $referral->id)
                ->where('identity_verified', 1)
                ->exists();

            $isActive = $hasRecentTrades && $isKycVerified;

            return [
                'id' => $referral->id,
                'uid' => $referral->uid,
                'email' => $referral->email,
                'created_at' => $referral->created_at,
                'trading_volume' => number_format($referral->trading_volume ?? 0, 8),
                'commission' => number_format($referral->referral_commission ?? 0, 8),
                'status' => $isActive ? 'Active' : 'Inactive'
            ];
        });

        return $referralList;
    }

    /**
     * Export referral list to CSV format
     * 
     * @param int $userId
     * @param array $params
     * @return array
     */
    public function exportReferralList($userId, $params = [])
    {
        $searchKey = Arr::get($params, 'search_key');
        $onlyActive = Arr::get($params, 'only_active', false) == 'true';
        $sortBy = Arr::get($params, 'sort_by', 'created_at');
        $sortType = Arr::get($params, 'sort_type', 'desc');

        $query = User::whereHas('affiliateTreesUp', function ($query) use ($userId) {
            $query->where('referrer_id', $userId)
                ->where('level', 1);
        })
        ->select([
            'id',
            'uid',
            'email',
            'created_at'
        ])
        ->withSum('reportTransactions as trading_volume', 'volume')
        ->addSelect([
            // Get commission that this referral generated for the referrer
            DB::raw("(SELECT COALESCE(SUM(usdt_value), 0) 
                      FROM client_referrer_histories 
                      WHERE user_id = {$userId} 
                      AND transaction_owner = users.id) as referral_commission")
        ]);

        // Apply search filter
        if ($searchKey) {
            $query->where(function ($q) use ($searchKey) {
                $q->where('uid', 'like', '%' . $searchKey . '%')
                  ->orWhere('email', 'like', '%' . $searchKey . '%');
            });
        }

        $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $today = Carbon::now()->endOfDay();

        // Apply active filter (both recent trades and KYC verified)
        if ($onlyActive === true) {
            $query->whereHas('completeTransactions', function ($q) use ($startOfLastMonth, $today) {
                $q->whereBetween('created_at', [$startOfLastMonth, $today]);
            })->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('user_security_settings')
                    ->whereColumn('user_security_settings.id', 'users.id')
                    ->where('user_security_settings.identity_verified', 1);
            });
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortType);

        $referrals = $query->get();

        // Transform data for export
        $rows = [];
        // Add header row
        $rows[] = [
            'UID',
            'Email',
            'Join Date',
            'Trading Volume (USDT)',
            'Commission (USDT)',
            'Status'
        ];

        // Add data rows
        foreach ($referrals as $referral) {
            // Check both trading activity and KYC status for Active status
            $hasRecentTrades = $referral->completeTransactions()
                ->whereBetween('created_at', [$startOfLastMonth, $today])
                ->exists();

            $isKycVerified = DB::table('user_security_settings')
                ->where('id', $referral->id)
                ->where('identity_verified', 1)
                ->exists();

            $isActive = $hasRecentTrades && $isKycVerified;

            $rows[] = [
                $referral->uid,
                $referral->email,
                $referral->created_at->format('Y-m-d H:i:s'),
                number_format($referral->trading_volume ?? 0, 8),
                number_format($referral->referral_commission ?? 0, 8),
                $isActive ? 'Active' : 'Inactive'
            ];
        }

        return $rows;
    }

    public function addClientReferralCommission($transaction)
    {
        $user = $transaction->user;
        if(!$user) {
            //case future
            throw new Exception('user_id not found');
        }
        $userIdNeedRefund = $user->referrer_id;
        $referralFee = 0;

        $coin = $transaction->transaction_type == Consts::ORDER_SIDE_BUY ? $transaction->coin : $transaction->currency;
        $commissionPercent = $user->referrerUser->userRate->referral_client_rate ?? 0;
        $isKyc = (($user->userSamsubKYC->status ?? "") == UserSamsubKyc::VERIFIED_STATUS);
        
        if ($userIdNeedRefund && $commissionPercent != 0 && $transaction->fee > 0 && is_null($user->referrerUser->is_partner) && $isKyc) {
            $amount = BigNumber::new($transaction->fee)->mul($commissionPercent)->div(100);
            $usdtValue = BigNumber::new($transaction->fee_usdt)->mul($commissionPercent)->div(100);
            $referralFee = $amount->toString();

            $this->refundClientAndCreateHistoryRecord(
                $userIdNeedRefund,
                $user->referrerUser->email,
                $amount,
                BigNumber::new($commissionPercent),
                strtoupper($coin),
                $transaction->id,
                $user,
                $usdtValue->toString(),
                $transaction->executed_date,
                $transaction->type
            );

            $this->updateReferralAmountReportTransaction($userIdNeedRefund, $usdtValue, 1);
            $this->createTotalFeeClientReferral($referralFee, $coin);

            // log referrer registration
            $referrer = User::find($userIdNeedRefund);
            if ($referrer && $referrer->referrer_code) {
                ReferrerRecentActivitiesLog::create([
                    'user_id' => $referrer->id,
                    'type'       => 'referral',
                    'target'     => collect([$transaction->id, $transaction->type, $transaction->executed_date])->toJson(),
                    'activities' => config('constants.referrer_message.co_received'),
                    'details'    => "{$usdtValue->toString()} USDT",
                    'actor'      => 'role:admin',
                    'log_at'     => Utils::currentMilliseconds(),
                ]);
            }
        }
    }

    public function refundClientAndCreateHistoryRecord(
        $userId,
        $email,
        $amount,
        $commissionRate,
        $coin,
        $transactionId,
        $transactionOwner,
        $usdtValue,
        $executedDate,
        $type
    ) {
        DB::beginTransaction();
        try {
            $hasRecord = CommissionBalance::find($userId);
            if (!$hasRecord) {
                CommissionBalance::create([
                    'id' => $userId,
                    'balance' => $usdtValue,
                    'available_balance' => $usdtValue,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $record = CommissionBalance::where('id', $userId)
                    ->lockForUpdate()
                    ->first();
                $record->update([
                        'balance' => DB::raw('balance + ' . $usdtValue),
                        'available_balance' => DB::raw('available_balance + ' . $usdtValue),
                        'updated_at' => now()
                ]);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger()->error($e);
        } finally {
            $data = [
                'user_id' => $userId,
                'email' => $email,
                'amount' => $amount,
                'coin' => $coin,
                'commission_rate' => $commissionRate,
                'transaction_owner' => $transactionOwner->id,
                'transaction_owner_email' => $transactionOwner->email,
                'type' => $type,
                'usdt_value' => $usdtValue,
                'complete_transaction_id' => $transactionId,
                'executed_date' => $executedDate,
            ];
            ClientReferrerHistory::create($data);
        }
    }

    public function updateReferralAmountReportTransaction($user_id, $referralAmount) {
        $today = Carbon::now()->toDateString();
        $hour = Carbon::now()->hour;
        if ($hour == 0) {
            $today = Carbon::yesterday()->toDateString();
        }

        ReportTransaction::upsert(
            [
                [
                    'date' => $today,
                    'user_id' => $user_id,
                    'referral_commission' => $referralAmount,
                ]
            ],
            ['date', 'user_id'],
            ['referral_commission' => DB::raw('referral_commission + ' . $referralAmount)]
        );
    }

    public function countTradeInMonth(array $directUsers): array
    {
        $start = Carbon::now()->subMonth()->startOfMonth()->toDateString();
        $end   = Carbon::now()->subMonth()->endOfMonth()->toDateString();

        $data = DB::table('report_transactions')
            ->whereIn('user_id', $directUsers)
            ->whereBetween('date', [$start, $end])
            ->where('volume', '>', 0)
            ->selectRaw('COUNT(DISTINCT user_id) AS trade_count, 
    SUM(volume) AS volume')
            ->first();

        return [
            'tradeCount' => $data->trade_count ?? 0,
            'volume' => $data->volume ?? 0,
            'handle_time' => Utils::currentMilliseconds()
        ];
    }

    public function referralLevel(array $directUsers): array
    {
        $check = $this->countTradeInMonth($directUsers);
        $levels = ReferrerClientLevel::filterProgress()->toArray();

        foreach (array_reverse($levels, true) as $level => $config) {
            $min = $config['trade_min'];
            // $max = $config['tradeRange']['max'];
            $requiredVolume = BigNumber::new($config['volume']);

            // Đạt level thấp hơn nếu chỉ đạt 1 tiêu chuẩn
            $tradeOk = $check['tradeCount'] >= $min;

            // Kiểm tra volume >= yêu cầu
            $volumeOk = BigNumber::new($check['volume'])->comp($requiredVolume) >= 0;

            if ($tradeOk && $volumeOk) {
                return [
                    'level' => $level,
                    'label' => $config['label'],
                    'rate' => $config['rate'],
                    'recalculate' => $check
                ];
            }
        }

        return [
            'level' => 0,
            'rate' => 0,
            'label' => $levels[0]['label'],
            'recalculate' => $check
        ];
    }


    public function reportReferralCommissionRanking($days, $userIds, $referrerId)
    {
        return match ($days) {
            'WEEKEND' => $this->reportReferralCommissionRankingWeekend($userIds, $referrerId),
            // 'DAILY' => $this->reportReferralCommissionRankingDaily($userIds, $referrerId),
            'SUBDAILY' => $this->reportReferralCommissionRankingSubDaily($userIds, $referrerId),
            default => []
        };
    }

    public function userReferralPassTrade($userIds, $referrerId) {
        $start = Carbon::now()->subDay()->startOfDay()->toDateString();
        $end = Carbon::now()->subDay()->endOfDay()->toDateString();

        return DB::table('report_transactions')
            ->whereIn('user_id', $userIds)
            ->where('volume', '>', 0)
            ->whereBetween('date', [$start, $end])
            ->count();
    }


    public function reportReferralCommissionRankingWeekend($userIds, $referrerId)
    {
        $start = Carbon::now()->startOfWeek()->toDateString();
        $end   = Carbon::now()->endOfWeek()->toDateString();

        $commission = DB::table('report_transactions')
            ->where('user_id', $referrerId)
            ->where('date', $start)
            ->selectRaw('referral_commission as commission')
            ->value('commission');

         $volume = DB::table('report_transactions')
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('SUM(volume) as volume')
            ->value('volume');

        return [
            'volume' => $volume ?? 0,
            'commission' => $commission ?? 0,
            'times' => [$start, $end]
        ];
    }

    public function reportReferralCommissionRankingSubDaily($userIds, $referrerId)
    {
        $start = Carbon::now()->subDay()->startOfDay()->toDateString();
        $end = Carbon::now()->subDay()->endOfDay()->toDateString();

       $commission = DB::table('report_transactions')
            ->where('user_id', $referrerId)
            ->where('date', $start)
            ->selectRaw('referral_commission as commission')
            ->value('commission');

        $volume = DB::table('report_transactions')
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('SUM(volume) as volume')
            ->value('volume');

        return [
            'volume' => $volume ?? 0,
            'commission' => $commission ?? 0,
            'times' => [$start, $end]
        ];
    }
}
