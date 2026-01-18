<?php

namespace App\Http\Services;

use App\Facades\DataExport;
use App\Models\AffiliateTrees;
use App\Models\ClientReferrerHistory;
use App\Models\User;
use App\Models\CompleteTransaction;
use App\Models\ReferrerClientLevel;
use App\Models\ReportTransaction;
use App\Models\UserRates;
use App\Models\UserSamsubKyc;
use App\Utils;
use App\Utils\BigNumber;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Arr;

class AdminReferralService
{
    /**
     * Get list of referrals with filters
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getReferralsList(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->select([
                'users.id',
                'users.uid',
                'users.email',
                'user_security_settings.identity_verified as is_kyc_verified',
                'referrer_users.uid as invited_by',
                DB::raw('COALESCE(SUM(complete_transactions.amount_usdt), 0) as total_trading_volume'),
                DB::raw('COALESCE(SUM(complete_transactions.fee_usdt), 0) as total_fee'),
                DB::raw("(SELECT COALESCE(SUM(usdt_value), 0) 
                  FROM client_referrer_histories 
                  WHERE user_id = referrer_users.id 
                  AND transaction_owner = users.id) as total_commission"),
                'affiliate_trees.created_at'
            ])
            ->leftJoin('user_security_settings', 'users.id', '=', 'user_security_settings.id')
            ->leftJoin('affiliate_trees', function ($join) {
                $join->on('users.id', '=', 'affiliate_trees.user_id')
                     ->where('affiliate_trees.level', '=', 1);
            })
            ->leftJoin('users as referrer_users', 'affiliate_trees.referrer_id', '=', 'referrer_users.id')
            ->leftJoin('complete_transactions', 'users.id', '=', 'complete_transactions.user_id')
            ->leftJoin('report_transactions', 'users.id', '=', 'report_transactions.user_id')
            ->whereNotNull('referrer_users.uid')
            ->groupBy('users.id', 'users.uid', 'users.email', 'referrer_users.uid', 'users.created_at')
            ->orderByDesc('users.created_at');

        $this->applySearchFilters($query, $filters);
        $this->applyDateFilters($query, $filters);

        $referrals = $query->paginate($filters['per_page'] ?? 10);
        $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $today = Carbon::now()->endOfDay();

        // Transform the result to include status
        $referrals->getCollection()->transform(function ($referral) use ($startOfLastMonth, $today) {
            // Check both trading activity and KYC status for Active status
            $hasRecentTrades = CompleteTransaction::where('user_id', $referral->id)
                ->whereBetween('created_at', [$startOfLastMonth, $today])
                ->exists();

            $isKycVerified = (bool)$referral->is_kyc_verified;
            $isActive = $hasRecentTrades && $isKycVerified;

            $total_profit = $referral->total_fee - $referral->total_commission;
            $profitRate = $referral->total_fee > 0 ? ($total_profit / $referral->total_fee * 100) : 0;

            return [
                'id' => $referral->id,
                'uid' => $referral->uid,
                'email' => $referral->email,
                'is_kyc_verified' => $isKycVerified,
                'invited_by' => $referral->invited_by,
                'total_trading_volume' => number_format($referral->total_trading_volume ?? 0, 8),
                'total_fee' => number_format($referral->total_fee ?? 0, 8),
                'total_commission' => number_format($referral->total_commission ?? 0, 8),
                'total_profit' => number_format($total_profit ?? 0, 8),
                'profit_rate' => number_format($profitRate, 2),
                'status' => $isActive ? 'Active' : 'Inactive',
                'created_at' => $referral->created_at
            ];
        });

        return $referrals;
    }

    /**
     * Apply search filters to query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return void
     */
    private function applySearchFilters($query, array $filters): void
    {
        if (!empty($filters['search_key'])) {
            $searchKey = $filters['search_key'];
            $query->where(function ($q) use ($searchKey) {
                $q->where('users.uid', 'like', "%{$searchKey}%")
                  ->orWhere('users.email', 'like', "%{$searchKey}%");
            });
        }
    }

    /**
     * Apply date range filters to query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return void
     */
    private function applyDateFilters($query, array $filters): void
    {
        if (!empty($filters['from_date'])) {
            $query->where('affiliate_trees.created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('affiliate_trees.created_at', '<=', $filters['to_date']);
        }
    }

    /**
     * Get list of referrers with filters
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getReferrersList(array $filters): LengthAwarePaginator
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        $query = User::query()
            ->select([
                'users.id',
                'users.uid',
                'users.email',
                DB::raw('COUNT(DISTINCT affiliate_trees.user_id) as number_of_referrals'),
                DB::raw('COALESCE(SUM(complete_transactions.amount_usdt), 0) as total_trading_volume'),
                DB::raw('COALESCE(SUM(complete_transactions.fee_usdt), 0) as total_fee'),
                'user_rates.referral_client_rate',
                'user_rates.referral_client_level',
                'users.created_at'
            ])
            ->addSelect([
                DB::raw('(SELECT COALESCE(SUM(CAST(referral_commission as DECIMAL(20,8))), 0) 
                          FROM report_transactions 
                          WHERE user_id = users.id) as total_commission')
            ])
            ->leftJoin('affiliate_trees', function ($join) {
                $join->on('users.id', '=', 'affiliate_trees.referrer_id')
                     ->where('affiliate_trees.level', '=', 1);
            })
            ->leftJoin('complete_transactions', 'affiliate_trees.user_id', '=', 'complete_transactions.user_id')
            ->leftJoin('user_rates', 'users.id', '=', 'user_rates.id')
            ->groupBy('users.id', 'users.uid', 'users.email', 'user_rates.referral_client_rate', 'user_rates.referral_client_level', 'users.created_at')
            ->having('number_of_referrals', '>', 0);

        $this->applyReferrerSearchFilters($query, $filters);
        $this->applyReferrerStatusFilter($query, $filters);
        $this->applyCommissionLevelFilter($query, $filters);
        
        // Apply sorting to computed fields in Database
        if (in_array($sortBy, ['number_of_referrals', 'total_trading_volume', 'total_commission', 'total_fee'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('users.created_at', $sortOrder);
        }

        $referrers = $query->paginate($filters['per_page'] ?? 10);

        // Transform the result to include calculated fields
        $transformedData = $referrers->getCollection()->map(function ($referrer) {
            $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
            $today = Carbon::now()->endOfDay();

            // Calculate active referrals count (both recent trades and KYC verified)
            $activeReferralsCount = AffiliateTrees::where('referrer_id', $referrer->id)
                ->where('level', 1)
                ->whereHas('userDown.completeTransactions', function ($query) use ($startOfLastMonth, $today) {
                    $query->whereBetween('created_at', [$startOfLastMonth, $today]);
                })
                ->whereHas('userDown', function ($query) {
                    $query->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('user_security_settings')
                            ->whereColumn('user_security_settings.id', 'users.id')
                            ->where('user_security_settings.identity_verified', 1);
                    });
                })
                ->count();

            // Calculate conversion rate
            $conversionRate = $referrer->number_of_referrals > 0 
                ? ($activeReferralsCount / $referrer->number_of_referrals * 100)
                : 0;

            // Calculate total profit
            $totalProfit = $referrer->total_fee - $referrer->total_commission;
            $profitRate = $referrer->total_fee > 0 ? ($totalProfit / $referrer->total_fee * 100) : 0;

            // Determine status (both recent trades and KYC verified)
            $hasRecentTrades = CompleteTransaction::where('user_id', $referrer->id)
                ->whereBetween('created_at', [$startOfLastMonth, $today])
                ->exists();

            $isKycVerified = DB::table('user_security_settings')
                ->where('id', $referrer->id)
                ->where('identity_verified', 1)
                ->exists();

            $isActive = $hasRecentTrades && $isKycVerified;

            // Determine level
            // $levels = config('constants.referrer_client_level');
            $levels = ReferrerClientLevel::filterProgress()->toArray();
            $level = 'Basic';
            foreach ($levels as $key => $levelConfig) {
                if ($referrer->referral_client_level == $key) {
                    $level = $levelConfig['label'];
                    break;
                }
            }

            return [
                'id' => $referrer->id,
                'uid' => $referrer->uid,
                'email' => $referrer->email,
                'number_of_referrals' => $referrer->number_of_referrals,
                'conversion_rate' => number_format($conversionRate, 2),
                'total_trading_volume' => number_format($referrer->total_trading_volume ?? 0, 8),
                'commission_rate' => $referrer->referral_client_rate ?? 0,
                'level' => $level,
                'total_fee' => number_format($referrer->total_fee ?? 0, 8),
                'total_commission' => number_format($referrer->total_commission ?? 0, 8),
                'total_profit' => number_format($totalProfit ?? 0, 8),
                'profit_rate' => number_format($profitRate, 2),
                'status' => $isActive ? 'Active' : 'Inactive',
                'created_at' => $referrer->created_at
            ];
        });

        // Apply sorting to computed fields in PHP
        if (in_array($sortBy, ['conversion_rate', 'commission_rate', 'total_profit'])) {
            $transformedData = $transformedData->sortBy($sortBy, SORT_REGULAR, $sortOrder === 'desc')->values();
        }

        // Replace the collection in paginator
        $referrers->setCollection($transformedData);

        return $referrers;
    }

    /**
     * Apply search filters for referrers
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return void
     */
    private function applyReferrerSearchFilters($query, array $filters): void
    {
        if (!empty($filters['search_key'])) {
            $searchKey = $filters['search_key'];
            $query->where(function ($q) use ($searchKey) {
                $q->where('users.uid', 'like', "%{$searchKey}%")
                  ->orWhere('users.email', 'like', "%{$searchKey}%");
            });
        }
    }

    /**
     * Apply status filter for referrers
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return void
     */
    private function applyReferrerStatusFilter($query, array $filters): void
    {
        $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $today = Carbon::now()->endOfDay();

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                // Active = has recent trades AND KYC verified
                $query->whereExists(function ($subquery) use ($startOfLastMonth, $today) {
                    $subquery->select(DB::raw(1))
                        ->from('complete_transactions')
                        ->whereRaw('complete_transactions.user_id = users.id')
                        ->whereBetween('complete_transactions.created_at', [$startOfLastMonth, $today]);
                })->whereExists(function ($subquery) {
                    $subquery->select(DB::raw(1))
                        ->from('user_security_settings')
                        ->whereRaw('user_security_settings.id = users.id')
                        ->where('user_security_settings.identity_verified', 1);
                });
            } elseif ($filters['status'] === 'inactive') {
                // Inactive = no recent trades OR not KYC verified
                $query->where(function ($q) use ($startOfLastMonth, $today) {
                    $q->whereNotExists(function ($subquery) use ($startOfLastMonth, $today) {
                        $subquery->select(DB::raw(1))
                            ->from('complete_transactions')
                            ->whereRaw('complete_transactions.user_id = users.id')
                            ->whereBetween('complete_transactions.created_at', [$startOfLastMonth, $today]);
                    })->orWhereNotExists(function ($subquery) {
                        $subquery->select(DB::raw(1))
                            ->from('user_security_settings')
                            ->whereRaw('user_security_settings.id = users.id')
                            ->where('user_security_settings.identity_verified', 1);
                    });
                });
            }
        }
    }

    /**
     * Apply commission level filter
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return void
     */
    private function applyCommissionLevelFilter($query, array $filters): void
    {
        if (isset($filters['commission_level'])) {
            if ($filters['commission_level'] == 0) {
                // For level 0, include both NULL and 0 values
                $query->where(function ($q) use ($filters) {
                    $q->whereNull('user_rates.referral_client_level')
                      ->orWhere('user_rates.referral_client_level', $filters['commission_level']);
                });
            } else {
                $query->where('user_rates.referral_client_level', $filters['commission_level']);
            }
        }
    }

    /**
     * Get detailed information of a referrer
     *
     * @param int $referrerId
     * @param array $filters
     * @return array
     */
    public function getReferrerDetails(int $referrerId): array
    {
        // Get general information
        $referrer = User::query()
            ->select([
                'users.id',
                'users.uid',
                'users.email',
                'users.created_at',
                'user_security_settings.identity_verified as is_kyc_verified',
                'user_rates.referral_client_rate',
                'user_rates.referral_client_level',
                DB::raw('COUNT(DISTINCT affiliate_trees.user_id) as number_of_referrals'),
                DB::raw('COALESCE(SUM(complete_transactions.amount_usdt), 0) as total_trading_volume'),
                DB::raw('COALESCE(SUM(complete_transactions.fee_usdt), 0) as total_fee')
            ])
            ->addSelect([
                DB::raw('(SELECT COALESCE(SUM(CAST(referral_commission as DECIMAL(20,8))), 0) 
                          FROM report_transactions 
                          WHERE user_id = users.id) as total_commission')
            ])
            ->leftJoin('user_security_settings', 'users.id', '=', 'user_security_settings.id')
            ->leftJoin('user_rates', 'users.id', '=', 'user_rates.id')
            ->leftJoin('affiliate_trees', function ($join) {
                $join->on('users.id', '=', 'affiliate_trees.referrer_id')
                     ->where('affiliate_trees.level', '=', 1);
            })
            ->leftJoin('complete_transactions', 'affiliate_trees.user_id', '=', 'complete_transactions.user_id')
            ->where('users.id', $referrerId)
            ->groupBy('users.id', 'users.uid', 'users.email', 'users.created_at', 'user_security_settings.identity_verified', 'user_rates.referral_client_rate', 'user_rates.referral_client_level')
            ->first();

        if (!$referrer) {
            return [];
        }

        $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $today = Carbon::now()->endOfDay();

        // Calculate active referrals count (both recent trades and KYC verified)
        $activeReferralsCount = AffiliateTrees::where('referrer_id', $referrerId)
            ->where('level', 1)
            ->whereHas('userDown.completeTransactions', function ($query) use ($startOfLastMonth, $today) {
                $query->whereBetween('created_at', [$startOfLastMonth, $today]);
            })
            ->whereHas('userDown', function ($query) {
                $query->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('user_security_settings')
                        ->whereColumn('user_security_settings.id', 'users.id')
                        ->where('user_security_settings.identity_verified', 1);
                });
            })
            ->count();

        // Calculate conversion rate
        $conversionRate = $referrer->number_of_referrals > 0 
            ? ($activeReferralsCount / $referrer->number_of_referrals * 100)
            : 0;

        // Calculate total profit and profit rate
        $totalProfit = $referrer->total_fee - $referrer->total_commission;
        $profitRate = $referrer->total_fee > 0 ? ($totalProfit / $referrer->total_fee * 100) : 0;

        // Determine status (both recent trades and KYC verified)
        $hasRecentTrades = CompleteTransaction::where('user_id', $referrerId)
            ->whereBetween('created_at', [$startOfLastMonth, $today])
            ->exists();

        $isKycVerified = (bool)$referrer->is_kyc_verified;
        $isActive = $hasRecentTrades && $isKycVerified;

        // Get referrer's referrer email
        $referrerEmail = User::where('id', function ($query) use ($referrerId) {
            $query->select('referrer_id')
                ->from('affiliate_trees')
                ->where('user_id', $referrerId)
                ->where('level', 1)
                ->first();
        })->value('email');

        // Get level label
        //$levels = config('constants.referrer_client_level');
        $levels = ReferrerClientLevel::filterProgress()->toArray();
        $level = 'Basic';
        foreach ($levels as $key => $levelConfig) {
            if ($referrer->referral_client_level == $key) {
                $level = $levelConfig['label'];
                break;
            }
        }

        return [
            'general' => [
                'id' => $referrer->id,
                'uid' => $referrer->uid,
                'email' => $referrer->email,
                'created_at' => $referrer->created_at,
                'is_kyc_verified' => $isKycVerified,
                'status' => $isActive ? 'Active' : 'Inactive',
                'referrer_email' => $referrerEmail,
                'number_of_referrals' => $referrer->number_of_referrals,
                'conversion_rate' => number_format($conversionRate, 2),
                'level' => $level,
                'commission_rate' => $referrer->referral_client_rate
            ],
            'statistics' => [
                'total_trading_volume' => number_format($referrer->total_trading_volume ?? 0, 8),
                'total_fee' => number_format($referrer->total_fee ?? 0, 8),
                'total_commission' => number_format($referrer->total_commission ?? 0, 8),
                'total_profit' => number_format($totalProfit ?? 0, 8),
                'profit_rate' => number_format($profitRate, 2)
            ]
        ];
    }

    /**
     * Get detailed information of a referrer
     *
     * @param int $referrerId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getReferrerTransactions(int $referrerId, array $filters = []): LengthAwarePaginator
    {
        // Get complete transactions from referrals
        $transactionsQuery = CompleteTransaction::query()
            ->select([
                'complete_transactions.created_at',
                'complete_transactions.type',
                'complete_transactions.amount_usdt as trading_volume',
                DB::raw('COALESCE(client_referrer_histories.commission_rate, 0) as commission_rate'),
                DB::raw('COALESCE(client_referrer_histories.usdt_value, 0) as commission'),
                'complete_transactions.fee_usdt as transaction_fee',
                'complete_transactions.user_id as referral_id',
                'users.uid as referral_uid',
                'users.email as referral_email'
            ])
            ->join('affiliate_trees', function ($join) {
                $join->on('complete_transactions.user_id', '=', 'affiliate_trees.user_id')
                     ->where('affiliate_trees.level', '=', 1);
            })
            ->join('users', 'complete_transactions.user_id', '=', 'users.id')
            ->leftJoin('client_referrer_histories', 'complete_transactions.id', '=', 'client_referrer_histories.complete_transaction_id')
            ->where('affiliate_trees.referrer_id', $referrerId);

        // Apply transaction type filter
        if (!empty($filters['type'])) {
            $transactionsQuery->where('complete_transactions.type', $filters['type']);
        }

        $transactions = $transactionsQuery->orderByDesc('complete_transactions.created_at')
            ->paginate($filters['per_page'] ?? 10);

        return $transactions;
    }

    /**
     * Get commission statistics with filters
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getCommissionStatistics(array $filters): LengthAwarePaginator
    {
        $query = CompleteTransaction::query()
            ->select([
                'complete_transactions.id as tx_id',
                'complete_transactions.created_at',
                'referrer.id as referrer_id',
                'referrer.uid as referrer_uid',
                'referrer.email as referrer_email',
                'users.uid as referral_id',
                'complete_transactions.type',
                'complete_transactions.amount_usdt as trading_volume',
                DB::raw('COALESCE(client_referrer_histories.commission_rate, 0) as commission_rate'),
                DB::raw('COALESCE(client_referrer_histories.usdt_value, 0) as commission'),
                'complete_transactions.fee_usdt as transaction_fee',
            ])
            ->join('users', 'complete_transactions.user_id', '=', 'users.id')
            ->join('affiliate_trees', function ($join) {
                $join->on('users.id', '=', 'affiliate_trees.user_id')
                     ->where('affiliate_trees.level', '=', 1);
            })
            ->join('users as referrer', 'referrer.id', '=', 'affiliate_trees.referrer_id')
            ->leftJoin('client_referrer_histories', 'complete_transactions.id', '=', 'client_referrer_histories.complete_transaction_id')
            ->orderByDesc('complete_transactions.created_at');

        // Apply search filters
        if (!empty($filters['search_key'])) {
            $searchKey = $filters['search_key'];
            $query->where(function ($q) use ($searchKey) {
                $q->where('referrer.uid', 'like', "%{$searchKey}%")
                  ->orWhere('referrer.email', 'like', "%{$searchKey}%");
            });
        }

        // Apply date filters
        if (!empty($filters['from_date'])) {
            $query->where('complete_transactions.created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('complete_transactions.created_at', '<=', $filters['to_date']);
        }

        $result = $query->paginate($filters['per_page'] ?? 10);

        // Transform data to add profit and profit_rate calculations
        $result->getCollection()->transform(function ($item) {
            // Calculate profit: transaction_fee - commission
            $profit = floatval($item->transaction_fee) - floatval($item->commission);

            // Calculate profit_rate: (profit / transaction_fee) * 100
            $profitRate = 0;
            if (floatval($item->transaction_fee) > 0) {
                $profitRate = ($profit / floatval($item->transaction_fee)) * 100;
            }

            // Add calculated fields to the item (force decimal format, not scientific notation)
            $item->profit = number_format($profit, 8);
            $item->profit_rate = number_format($profitRate, 2);

            return $item;
        });

        return $result;
    }

    public function getDistributedCommissionOverview() {
        $query = DB::table("affiliate_trees", "a")
            ->join("report_transactions AS b", "a.user_id", "=", "b.user_id")
            ->join("user_samsub_kyc AS c", "a.user_id", "=", "c.user_id")
            ->where("a.level", "=", 1)
            ->where("b.volume", ">", "0")
            ->where("c.status", UserSamsubKyc::VERIFIED_STATUS);

        $countActiveReferrer = $query->distinct("a.user_id")->count("a.user_id");

        $total = ReportTransaction::selectRaw("SUM(fee) AS s_fee, SUM(volume) AS s_volume, SUM(commission) AS s_commission, SUM(referral_commission) AS s_referral_commission")->first();
        $total_referrer = AffiliateTrees::with('userUp')
        ->withSum('reportTransactionsUp as total_commission', 'referral_commission')
        ->where('level', 1)
        ->get();

        $total_referrals = AffiliateTrees::with('userDown')
        ->withSum('reportTransactions as total_volume', 'volume')
        ->withSum('reportTransactions as total_fee', 'fee')
        ->where('level', 1)
        ->get();

        $total_referrals_fee = BigNumber::new($total_referrals->sum('total_fee'));
        $total_referrals_volume = BigNumber::new($total_referrals->sum('total_volume'));
        $total_referrals_commission = BigNumber::new($total_referrer->sum('total_commission'));

        $profit_referrals = $total_referrals_fee->sub($total_referrals_commission);

        if ($countActiveReferrer == 0) {$pcaPerReferrer = null;} 
        else {$pcaPerReferrer = BigNumber::new($total->s_referral_commission ?? 0)->div($countActiveReferrer)->toString();}
        $profit = BigNumber::new($total->s_fee ?? 0)->sub(BigNumber::new($total->s_commission ?? 0))->sub(BigNumber::new($total->s_referral_commission))->toString();

        return [
            "fee" => $total->s_fee ?? "0",
            "feeReferrals" => $total_referrals_fee->toString(),
            "volume" => $total->s_volume ?? "0",
            "volumeReferrals" => $total_referrals_volume->toString(),
            "commission" => $total->s_referral_commission ?? "0",
            "profit" => $profit,
            "profitReferrals" => $profit_referrals->toString(),
            "referralsRoi" => $total_referrals_fee->isPositive() ? $profit_referrals->div($total_referrals_fee)->toString() : 0,
            "referralsFeeDivTotalFee" => BigNumber::new($total->s_fee)->isPositive() ? $total_referrals_fee->div($total->s_fee)->toString() : 0,
            "totalCommissionReferralsDivTotalFeeReferrals" => $total_referrals_fee->isPositive() ? $total_referrals_commission->div($total_referrals_fee)->toString() : 0, //"{$total_referrals_commission}/{$total_referrals_fee}",
            "pcaPerReferrer" => $pcaPerReferrer,
        ];
    }

    public function distributedCommissionOverviewExport($request) {
        $now = Utils::currentMilliseconds();
        $ext = Arr::get($request, 'ext', 'csv');
        $data = $this->getDistributedCommissionOverview();
        
        $headers = array_keys($data);
        $data = array_values($data);
        $data = [$headers, $data];

        $params = [
            'fileName' => "distributedCommissionOverviewExport{$now}",
                    'data' => $data,
                    'ext' => $ext,
                    'headers' => [
                        'X-Custom-Export' => 'Yes'
                    ],
                ];

        return DataExport::export($params);
    }

    public function getDistributedCommissionStatistics($sdate, $edate, $tz = 'UTC') {
        $sdate = Carbon::parse($sdate);
        $edate = Carbon::parse($edate);

        $periodInfo = detectPeriodType($sdate, $edate);
        $periodType = collect($periodInfo)->except('range_days')->filter()->keys()->first();

        return match($periodType) {
            'is_today' => $this->commissionStatisticsWithFormat($sdate, $edate, 'H:i', $tz),
            'is_this_year', 
            'is_last_year' => $this->commissionStatisticsWithFormat($sdate, $edate, 'Y-m', $tz),
            default => $this->commissionStatisticsRangeDates($sdate, $edate),
        };

        
    }
    public function commissionStatisticsWithFormat($sdate, $edate, $format, $tz) {
        $sdate = Carbon::parse($sdate)->startOfDay('UTC');
        $edate = Carbon::parse($edate)->endOfDay('UTC');

        $recordsFee = CompleteTransaction::query()
        ->whereBetWeen('created_at', [$sdate, $edate])
        ->where('fee_usdt', '>', 0)
        ->get();
        
        $fee = $recordsFee->groupBy(function($i) use($format, $tz) { return Carbon::parse($i->created_at)->timezone($tz)->format($format);})->map(function($group) {
            return $group->sum('fee_usdt');
        })->sortKeys();

        $recordCommission = ClientReferrerHistory::query()
        ->whereBetWeen('created_at', [$sdate, $edate])
        ->where('usdt_value', '>', 0)
        ->get();

        $commissionRaw = $recordCommission->groupBy(function($i) use($format, $tz) { return Carbon::parse($i->created_at)->timezone($tz)->format($format);})->map(function($group) {
            return $group->sum('usdt_value');
        })->sortKeys();

        $commission = $fee->keys()->mapWithKeys(function ($key) use ($commissionRaw) {
            return [$key => $commissionRaw->get($key, 0)];
        });


        $profit = $fee->mapWithKeys(function ($value, $key) use ($commission) {
            $_fee = BigNumber::new($value);
            $com = BigNumber::new($commission[$key] ?? 0);
            return [$key => $_fee->sub($com)->toString()];

        })->sortKeys();

        return ['fee' => $fee, 'commission' => $commission, 'profit' => $profit];
    }

    public function commissionStatisticsRangeDates($sdate, $edate) {
        $rangeDates = CarbonPeriod::create($sdate, $edate);
        $items = ReportTransaction::whereBetween("date", [$sdate, $edate])
            ->selectRaw("date, 
            COALESCE(SUM(fee), 0) AS s_fee,
            COALESCE(SUM(commission), 0) AS s_commission,
            COALESCE(SUM(referral_commission), 0) AS s_referral_commission")
            ->groupBy("date")
            ->orderBy("date", "asc")
            ->get()
            ->keyBy('date');

        $days = collect($rangeDates)->map(function($i) use($items) {
            $date = $i->format('Y-m-d');
            $item = $items[$date] ?? null;

            $fee = BigNumber::new($item->s_fee ?? 0);
            $commission = BigNumber::new($item->s_commission ?? 0);
            $referral = BigNumber::new($item->s_referral_commission ?? 0);

            $profit = $fee->sub($commission)->sub($referral);

            return [
                "date" => $date,
                "fee" => $fee->toString(),
                "commission" => $referral->toString(),
                "profit" => $profit->toString(),
                "rate" => $fee->isPositive() ? $profit->mul(100)->div($fee)->toString() : "00.00000000"
            ];
        });
        return $days;
    }

    /**
     * Export referrals list to CSV
     *
     * @param array $filters
     * @return array
     */
    public function exportReferralsList(array $filters): array
    {
        $query = User::query()
            ->select([
                'users.id',
                'users.uid',
                'users.email',
                'user_security_settings.identity_verified as is_kyc_verified',
                'referrer_users.uid as invited_by',
                DB::raw('COALESCE(SUM(complete_transactions.amount_usdt), 0) as total_trading_volume'),
                DB::raw('COALESCE(SUM(complete_transactions.fee_usdt), 0) as total_fee'),
                DB::raw('COALESCE(SUM(report_transactions.referral_commission), 0) as total_commission'),
                'users.created_at'
            ])
            ->leftJoin('user_security_settings', 'users.id', '=', 'user_security_settings.id')
            ->leftJoin('affiliate_trees', function ($join) {
                $join->on('users.id', '=', 'affiliate_trees.user_id')
                     ->where('affiliate_trees.level', '=', 1);
            })
            ->leftJoin('users as referrer_users', 'affiliate_trees.referrer_id', '=', 'referrer_users.id')
            ->leftJoin('complete_transactions', 'users.id', '=', 'complete_transactions.user_id')
            ->leftJoin('report_transactions', 'users.id', '=', 'report_transactions.user_id')
            ->whereNotNull('referrer_users.uid')
            ->groupBy('users.id', 'users.uid', 'users.email', 'referrer_users.uid', 'users.created_at')
            ->orderByDesc('users.created_at');

        $this->applySearchFilters($query, $filters);
        $this->applyDateFilters($query, $filters);

        $referrals = $query->get();

        // Transform data for export (return JSON list like getReferralsList)
        $exportData = [];
        $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $today = Carbon::now()->endOfDay();

        foreach ($referrals as $referral) {
            // Check both trading activity and KYC status for Active status
            $hasRecentTrades = CompleteTransaction::where('user_id', $referral->id)
                ->whereBetween('created_at', [$startOfLastMonth, $today])
                ->exists();

            $isKycVerified = (bool)$referral->is_kyc_verified;
            $isActive = $hasRecentTrades && $isKycVerified;

            $total_profit = $referral->total_fee - $referral->total_commission;
            $profitRate = $referral->total_fee > 0 ? ($total_profit / $referral->total_fee * 100) : 0;

            $exportData[] = [
                'id' => $referral->id,
                'uid' => $referral->uid,
                'email' => $referral->email,
                'is_kyc_verified' => $isKycVerified,
                'invited_by' => $referral->invited_by,
                'total_trading_volume' => number_format($referral->total_trading_volume ?? 0, 8),
                'total_fee' => number_format($referral->total_fee ?? 0, 8),
                'total_commission' => number_format($referral->total_commission ?? 0, 8),
                'total_profit' => number_format($total_profit ?? 0, 8),
                'profit_rate' => number_format($profitRate, 2),
                'status' => $isActive ? 'Active' : 'Inactive',
                'created_at' => Carbon::parse($referral->created_at)->toISOString()
            ];
        }

        return $exportData;
    }

    /**
     * Export referrers list to CSV
     *
     * @param array $filters
     * @return array
     */
    public function exportReferrersList(array $filters): array
    {
        $query = User::query()
            ->select([
                'users.id',
                'users.uid',
                'users.email',
                DB::raw('COUNT(DISTINCT affiliate_trees.user_id) as number_of_referrals'),
                DB::raw('COALESCE(SUM(complete_transactions.amount_usdt), 0) as total_trading_volume'),
                DB::raw('COALESCE(SUM(complete_transactions.fee_usdt), 0) as total_fee'),
                'user_rates.referral_client_rate',
                'user_rates.referral_client_level',
                'users.created_at'
            ])
            ->addSelect([
                DB::raw('(SELECT COALESCE(SUM(CAST(referral_commission as DECIMAL(20,8))), 0) 
                          FROM report_transactions 
                          WHERE user_id = users.id) as total_commission')
            ])
            ->leftJoin('affiliate_trees', function ($join) {
                $join->on('users.id', '=', 'affiliate_trees.referrer_id')
                     ->where('affiliate_trees.level', '=', 1);
            })
            ->leftJoin('complete_transactions', 'affiliate_trees.user_id', '=', 'complete_transactions.user_id')
            ->leftJoin('user_rates', 'users.id', '=', 'user_rates.id')
            ->groupBy('users.id', 'users.uid', 'users.email', 'user_rates.referral_client_rate', 'user_rates.referral_client_level', 'users.created_at')
            ->having('number_of_referrals', '>', 0);

        $this->applyReferrerSearchFilters($query, $filters);
        $this->applyReferrerStatusFilter($query, $filters);
        $this->applyCommissionLevelFilter($query, $filters);

        $referrers = $query->orderByDesc('users.created_at')->get();

        // Transform data for export (return JSON list like getReferrersList)
        $exportData = [];
        $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $today = Carbon::now()->endOfDay();

        // Add data rows
        foreach ($referrers as $referrer) {
            // Calculate active referrals count (both recent trades and KYC verified)
            $activeReferralsCount = AffiliateTrees::where('referrer_id', $referrer->id)
                ->where('level', 1)
                ->whereHas('userDown.completeTransactions', function ($query) use ($startOfLastMonth, $today) {
                    $query->whereBetween('created_at', [$startOfLastMonth, $today]);
                })
                ->whereHas('userDown', function ($query) {
                    $query->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('user_security_settings')
                            ->whereColumn('user_security_settings.id', 'users.id')
                            ->where('user_security_settings.identity_verified', 1);
                    });
                })
                ->count();

            // Calculate conversion rate
            $conversionRate = $referrer->number_of_referrals > 0 
                ? ($activeReferralsCount / $referrer->number_of_referrals * 100)
                : 0;

            // Calculate total profit
            $totalProfit = $referrer->total_fee - $referrer->total_commission;
            $profitRate = $referrer->total_fee > 0 ? ($totalProfit / $referrer->total_fee * 100) : 0;

            // Determine status (both recent trades and KYC verified)
            $hasRecentTrades = CompleteTransaction::where('user_id', $referrer->id)
                ->whereBetween('created_at', [$startOfLastMonth, $today])
                ->exists();

            $isKycVerified = DB::table('user_security_settings')
                ->where('id', $referrer->id)
                ->where('identity_verified', 1)
                ->exists();

            $isActive = $hasRecentTrades && $isKycVerified;

            // Determine level
            $levels = ReferrerClientLevel::filterProgress()->toArray();
            $level = 'Basic';
            foreach ($levels as $key => $levelConfig) {
                if ($referrer->referral_client_level == $key) {
                    $level = $levelConfig['label'];
                    break;
                }
            }

            $exportData[] = [
                'id' => $referrer->id,
                'uid' => $referrer->uid,
                'email' => $referrer->email,
                'number_of_referrals' => $referrer->number_of_referrals,
                'conversion_rate' => number_format($conversionRate, 2),
                'total_trading_volume' => number_format($referrer->total_trading_volume ?? 0, 8),
                'commission_rate' => $referrer->referral_client_rate ?? 0,
                'level' => $level,
                'total_fee' => number_format($referrer->total_fee ?? 0, 8),
                'total_commission' => number_format($referrer->total_commission ?? 0, 8),
                'total_profit' => number_format($totalProfit ?? 0, 8),
                'profit_rate' => number_format($profitRate, 2),
                'status' => $isActive ? 'Active' : 'Inactive',
                'created_at' => Carbon::parse($referrer->created_at)->toISOString()
            ];
        }

        return $exportData;
    }

    /**
     * Export commission statistics to CSV
     *
     * @param array $filters
     * @return array
     */
    public function exportCommissionStatistics(array $filters): array
    {
        $query = CompleteTransaction::query()
            ->select([
                'complete_transactions.id as tx_id',
                'complete_transactions.created_at',
                'referrer.id as referrer_id',
                'referrer.uid as referrer_uid',
                'referrer.email as referrer_email',
                'users.uid as referral_id',
                'complete_transactions.type',
                'complete_transactions.amount_usdt as trading_volume',
                DB::raw('COALESCE(client_referrer_histories.commission_rate, 0) as commission_rate'),
                DB::raw('COALESCE(client_referrer_histories.usdt_value, 0) as commission'),
                'complete_transactions.fee_usdt as transaction_fee',
            ])
            ->join('users', 'complete_transactions.user_id', '=', 'users.id')
            ->join('affiliate_trees', function ($join) {
                $join->on('users.id', '=', 'affiliate_trees.user_id')
                     ->where('affiliate_trees.level', '=', 1);
            })
            ->join('users as referrer', 'referrer.id', '=', 'affiliate_trees.referrer_id')
            ->leftJoin('client_referrer_histories', 'complete_transactions.id', '=', 'client_referrer_histories.complete_transaction_id')
            ->orderByDesc('complete_transactions.created_at');

        // Apply search filters
        if (!empty($filters['search_key'])) {
            $searchKey = $filters['search_key'];
            $query->where(function ($q) use ($searchKey) {
                $q->where('referrer.uid', 'like', "%{$searchKey}%")
                  ->orWhere('referrer.email', 'like', "%{$searchKey}%");
            });
        }

        // Apply date filters
        if (!empty($filters['from_date'])) {
            $query->where('complete_transactions.created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('complete_transactions.created_at', '<=', $filters['to_date']);
        }

        $statistics = $query->get();

        // Transform data for export (return JSON list like getCommissionStatistics)
        $exportData = [];

        foreach ($statistics as $stat) {
            // Calculate profit: transaction_fee - commission
            $profit = floatval($stat->transaction_fee) - floatval($stat->commission);

            // Calculate profit_rate: (profit / transaction_fee) * 100
            $profitRate = 0;
            if (floatval($stat->transaction_fee) > 0) {
                $profitRate = ($profit / floatval($stat->transaction_fee)) * 100;
            }

            $exportData[] = [
                'tx_id' => $stat->tx_id,
                'created_at' => Carbon::parse($stat->created_at)->toISOString(),
                'referrer_id' => $stat->referrer_id,
                'referrer_uid' => $stat->referrer_uid,
                'referrer_email' => $stat->referrer_email,
                'referral_id' => $stat->referral_id,
                'type' => $stat->type,
                'trading_volume' => number_format($stat->trading_volume ?? 0, 8),
                'commission_rate' => number_format($stat->commission_rate ?? 0, 4),
                'commission' => number_format($stat->commission ?? 0, 8),
                'transaction_fee' => number_format($stat->transaction_fee ?? 0, 8),
                'profit' => number_format($profit, 8),
                'profit_rate' => number_format($profitRate, 2)
            ];
        }

        return $exportData;
    }

    /**
     * Export referrer transactions to CSV
     *
     * @param int $referrerId
     * @param array $filters
     * @return array
     */
    public function exportReferrerTransactions(int $referrerId, array $filters = []): array
    {
        // Get referrer transactions
        $transactionsQuery = CompleteTransaction::query()
            ->select([
                'complete_transactions.created_at',
                'complete_transactions.type',
                'complete_transactions.amount_usdt as trading_volume',
                DB::raw('COALESCE(client_referrer_histories.commission_rate, 0) as commission_rate'),
                DB::raw('COALESCE(client_referrer_histories.usdt_value, 0) as commission'),
                'complete_transactions.user_id as referral_id',
                'users.uid as referral_uid',
                'users.email as referral_email'
            ])
            ->join('affiliate_trees', function ($join) {
                $join->on('complete_transactions.user_id', '=', 'affiliate_trees.user_id')
                     ->where('affiliate_trees.level', '=', 1);
            })
            ->join('users', 'complete_transactions.user_id', '=', 'users.id')
            ->leftJoin('client_referrer_histories', 'complete_transactions.id', '=', 'client_referrer_histories.complete_transaction_id')
            ->where('affiliate_trees.referrer_id', $referrerId);

        // Apply transaction type filter
        if (!empty($filters['type'])) {
            $transactionsQuery->where('complete_transactions.type', $filters['type']);
        }

        $transactions = $transactionsQuery->orderByDesc('complete_transactions.created_at')->get();

        // Transform data for export (return JSON list like getReferrerTransactions)
        $exportData = [];

        foreach ($transactions as $transaction) {
            $exportData[] = [
                'created_at' => Carbon::parse($transaction->created_at)->toISOString(),
                'type' => $transaction->type,
                'trading_volume' => number_format($transaction->trading_volume ?? 0, 8),
                'commission_rate' => number_format($transaction->commission_rate ?? 0, 4),
                'commission' => number_format($transaction->commission ?? 0, 8),
                'referral_id' => $transaction->referral_id,
                'referral_uid' => $transaction->referral_uid,
                'referral_email' => $transaction->referral_email
            ];
        }

        return $exportData;
    }
}
