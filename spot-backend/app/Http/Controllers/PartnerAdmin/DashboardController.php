<?php

namespace App\Http\Controllers\PartnerAdmin;

use App\Consts;
use App\Http\Controllers\Controller;
use App\Http\Services\ActivityHistoryService;
use App\Http\Services\BalanceService;
use App\Http\Services\PriceService;
use App\Models\ActivityHistory;
use App\Models\AffiliateTrees;
use App\Models\CoinsConfirmation;
use App\Models\CompleteTransaction;
use App\Models\PartnerRequest;
use App\Models\ReferrerHistory;
use App\Models\ReportTransaction;
use App\Models\User;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use DB;

class DashboardController extends Controller
{
    private $priceService, $balanceService, $activityHistoryService;

    public function __construct(PriceService $priceService, BalanceService $balanceService, ActivityHistoryService $activityHistoryService) {
        $this->priceService = $priceService;
        $this->balanceService = $balanceService;
        $this->activityHistoryService = $activityHistoryService;
    }
    public function getPartnerStatistics() {
        $today = Carbon::now();
        $start = $today->copy()->startOfDay();
        $end = $today->copy()->endOfDay();
        $totalPartner = User::whereNotNull('is_partner')->count();
        $todaysNewPartner = User::whereNotNull('is_partner')
            ->whereBetween('partner_registered_at', [$start, $end])
            ->count();
        $pendingPartnerRequests = PartnerRequest::where(['type' => Consts::PARTNER_REQUEST_PARTNER_CHANGE_RATE, 'status' => Consts::PARTNER_REQUEST_PENDING])
            ->count();
        
        $data = [
            'totalPartner' => $totalPartner,
            'todaysNewPartner' => $todaysNewPartner,
            'pendingPartnerRequests' => $pendingPartnerRequests
        ];

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function getCommissionStatistics(Request $request) {
        $params = $request->all();
        $timeFilter = Arr::get($params, 'timeFilter', '');
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');

        $timeFilterOption = $this->getTimeFilterOption();
        $head = [
            'filter' => [
                'timeFilter' => [
                    'type' => 'select',
                    'option' => $timeFilterOption,
                    'value' => $timeFilter
                    ],
                'sdate' => $sdate,
                'edate' => $edate,
            ],
        ];
        
        $startEnd = [];
        if($timeFilter != '') {$startEnd = Utils::getStartEndByTimeFilter($timeFilter);}

        $data = ReportTransaction::whereHas('user', function ($query) {
                $query->where('type', '<>', Consts::USER_TYPE_BOT);
            })
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                    $query->whereBetween('date', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
                }, function ($query) use ($startEnd) {
                    $query->when($startEnd, function ($query, $startEnd) {
                        $query->whereBetween('date', [$startEnd['start'], $startEnd['end']]);
                    });
                })
            ->selectRaw("IF(SUM(volume) IS NULL, 0, SUM(volume)) AS totalTradingVolume, 
                IF(SUM(fee) IS NULL, 0, SUM(fee)) AS totalFee, 
                IF(SUM(commission) IS NULL, 0, SUM(commission)) AS totalCommission, 
                IF(SUM(fee - commission) IS NULL, 0, SUM(fee - commission)) AS totalProfit")
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'head' => $head,
            'data' => $data
        ]);
    }

    public function getTopListBalances(Request $request) {
        $token = explode(' ', $request->header('Authorization'))[1];
        
        $currencies = CoinsConfirmation::query()
            ->select('coin')
            ->pluck('coin');

        $userIds = User::whereNotNull('is_partner')->pluck('id')->all();

        $futureBalance = $this->balanceService->getFutureBalance($token, $userIds);

        $balanceType = Consts::TYPE_EXCHANGE_BALANCE;
        $num = 1;
        $unionQuery = '';
        foreach ($currencies as $currency) {

            $currencyTable = $this->balanceService->getTableWithType($balanceType, $currency);

            // Build query
            $subQuery = DB::connection('master')
                ->table($currencyTable, 'a')
                ->rightJoin('users AS b', 'a.id', '=', 'b.id')
                ->leftJoin('users AS c', 'b.referrer_id', '=', 'c.id')
                ->whereIn('b.id', $userIds)
                ->select([
                    DB::raw("'{$currency}' AS asset"),
                    'a.balance',
                    'a.available_balance',
                    'b.id AS account_id',
                    'b.email',
                    'c.id AS invite_id',
                    'c.email AS invite_email'
                ]);

            if ($num == 1) {$unionQuery = $subQuery;}
            else $unionQuery->union($subQuery);
            $num++;
        }

        $balances = DB::table($unionQuery)
            ->select([
                'asset',
                'balance',
                'available_balance',
                'account_id',
                'email',
                'invite_id',
                'invite_email',
            ])
            ->get();
        
        $listBalance = [];
        $listSpotPriceVsUsdt = [];
        foreach ($balances as $item) {
            
            if (empty($listSpotPriceVsUsdt[$item->asset])) {
                if (in_array($item->asset, ['usdt', 'usd'])) {$listSpotPriceVsUsdt[$item->asset] = 1;}
                else {$listSpotPriceVsUsdt[$item->asset] = $this->priceService->getCurrentPrice('usdt', $item->asset)->price ?? 0;}
            }

            // $amount = BigNumber::new($item->balance ?? '0')->mul($listSpotPriceVsUsdt[$item->asset])->toString();
            $amount = BigNumber::new(safeBigNumberInput($item->balance))->mul(safeBigNumberInput($listSpotPriceVsUsdt[$item->asset]))->toString();
            if (empty($listBalance[$item->account_id])) {
                $listBalance[$item->account_id] = [
                    'accountId' => $item->account_id,
                    'email' => $item->email,
                    'inviteId' => $item->invite_id,
                    'inviteEmail' => $item->invite_email,
                    'amount' => $amount,
                ];    
            } else {
                // $listBalance[$item->account_id]['amount'] = BigNumber::new($listBalance[$item->account_id]['amount'])->add($amount)->toString();
                $listBalance[$item->account_id]['amount'] = BigNumber::new(safeBigNumberInput($listBalance[$item->account_id]['amount']))->add(safeBigNumberInput($amount))->toString();

            }
        }

        foreach ($listBalance as $accountId => $item) {
            if(!empty($futureBalance[$accountId])) {
                // $listBalance[$accountId]['amount'] = BigNumber::new($listBalance[$accountId]['amount'])->add($futureBalance[$accountId]['usdtBalance'])->toString();
                $listBalance[$accountId]['amount'] = BigNumber::new(safeBigNumberInput($listBalance[$accountId]['amount']))->add(safeBigNumberInput($futureBalance[$accountId]['usdtBalance']))->toString();
            }
        }

        $balancesCollection = collect(array_values($listBalance))
            ->sortByDesc('amount')->take(Consts::TOP_LIMIT)->all();

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => array_values($balancesCollection)
        ]);
    }

    public function getTopListReferrer() {
        $items = AffiliateTrees::with('userUp')
            ->whereHas('userUp', function ($query) {
                    $query->whereNotNull('is_partner')
                        ->whereNull('referrer_id');
                })
            ->groupBy('referrer_id')
            ->selectRaw("referrer_id,
                COUNT(*) AS total_referral,
                SUM(IF(level = 1, 1, 0)) AS count_direct,
                SUM(IF(level > 1, 1, 0)) AS count_indirect")
            ->limit(Consts::TOP_LIMIT)
            ->get();
        
        $data = [];
        foreach ($items as $item) {
            $_item = [
                'accountId' => $item->referrer_id,
                'email' => $item->userUp->email,
                'totalReferral' => $item->total_referral,
                'direct' => $item->count_direct,
                'indirect' => $item->count_indirect
            ];

            $data[] = $_item;
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function getTopListVolumeCommission(Request $request) {
        $params = $request->all();
        $sort = Arr::get($params, 'sort', '');
        $timeFilter = Arr::get($params, 'timeFilter', '');
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');

        if(!in_array($sort, ['totalTradingVolume', 'totalCommission'])) $sort = 'totalTradingVolume';

        $timeFilterOption = $this->getTimeFilterOption();
        $head = [
            'filter' => [
                'timeFilter' => [
                    'type' => 'select',
                    'option' => $timeFilterOption,
                    'value' => $timeFilter
                    ],
                'sdate' => $sdate,
                'edate' => $edate,
            ],
            'sort' => $sort
        ];
        
        $startEnd = [];
        if($timeFilter != '') {$startEnd = Utils::getStartEndByTimeFilter($timeFilter);}

        $items = ReportTransaction::with('user')
            ->whereHas('user', function ($query) {
                    $query->where('type', '<>', Consts::USER_TYPE_BOT);
                })
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                    $query->whereBetween('date', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
                }, function ($query) use ($startEnd) {
                    $query->when($startEnd, function ($query, $startEnd) {
                        $query->whereBetween('date', [$startEnd['start'], $startEnd['end']]);
                    });
                })
            ->groupBy('user_id')
            ->orderByDesc($sort)
            ->selectRaw("user_id, 
                SUM(volume) AS totalTradingVolume,
                SUM(commission) AS totalCommission")
            ->limit(Consts::TOP_LIMIT)
            ->get();

        $data = [];
        foreach ($items as $item) {
            $_item = [
                'accountId' => $item->user_id,
                'email' => $item->user->email,
                'inviteId' => $item->user->referrer_id,
                'inviteEmail' => $item->user->referrerUser ? $item->user->referrerUser->email : null,
                'totalTradingVolume' => $item->totalTradingVolume,
                'totalCommission' => $item->totalCommission,
            ];
            $data[] = $_item;
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'head' => $head,
            'data' => $data
        ]);
    }

    private function getTimeFilterOption() {
        foreach (Consts::TIME_FILTER_OPTION as $k => $v ) {
            $timeFilterOption[] = [
                'label' => $v,
                'value' => $k
            ];
        }

        return $timeFilterOption;
    }

    public function getActivityHistory(Request $request) {
        $params = $request->all();
        $timeFilter = Arr::get($params, 'timeFilter', '');
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $timeFilterOption = $this->getTimeFilterOption();
        $head = [
            'filter' => [
                'timeFilter' => [
                    'type' => 'select',
                    'option' => $timeFilterOption,
                    'value' => $timeFilter
                    ],
                'sdate' => $sdate,
                'edate' => $edate,
            ],
            'page' => $page,
            'limit' => $limit
        ];
        
        $startEnd = [];
        if($timeFilter != '') {$startEnd = Utils::getStartEndByTimeFilter($timeFilter);}

        $items = ActivityHistory::when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                    $query->whereBetween('created_at', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
                }, function ($query) use ($startEnd) {
                    $query->when($startEnd, function ($query, $startEnd) {
                        $query->whereBetween('created_at', [$startEnd['start'], $startEnd['end']]);
                    });
                })
            ->orderByDesc('created_at')
            ->paginate($limit);

        $items->setCollection($items->map(function($item) {
            $title = $this->activityHistoryService->getDetail($item->type, $item->target_id);
            $_item = [
                'id' => $item->id,
                'title' => $title,
                'time' => $item->created_at
            ];
            return $_item;
        }));

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'head' => $head,
            'list' => $items->appends($params)
        ]);
    }

    public function getNewPartnerChart(Request $request) {
        $params = $request->all();
        $timeFilter = Arr::get($params, 'timeFilter', Consts::TIME_FILTER_THIS_YEAR);

        if (empty(Consts::TIME_FILTER_OPTION[$timeFilter])) {
            return response()->json([
                'success' => false,
                'message' => 'timeFilter not found',
            ], 404);
        }

        $startEnd = Utils::getStartEndByTimeFilter($timeFilter, 'chart');

        $timeFilterOption = $this->getTimeFilterOption();
        $head = [
            'filter' => [
                'timeFilter' => [
                    'type' => 'select',
                    'option' => $timeFilterOption,
                    'value' => $timeFilter
                ],
            ]
        ];

        $query = User::whereBetween('partner_registered_at', [$startEnd['start'], $startEnd['end']]);
        $labels = Utils::selectByTimeFilter($query, $timeFilter, 'partner_registered_at', 1);
        $arrayVal = $query->first()->toArray();

        $chartValue = [];
        foreach ($arrayVal as $val) {$chartValue[] = $val ?? '0';}

        if (!in_array($timeFilter, [Consts::TIME_FILTER_THIS_MONTH, Consts::TIME_FILTER_LAST_MONTH])) {
            $labels = [];
            foreach (Consts::CHART_COLUMN_LABEL[$timeFilter] as $label) {$labels[] = [$label];}
        }

        $data = [
            'labels' => $labels,
            'values' => $chartValue
        ];

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'head' => $head,
            'data' => $data
        ]);
    }

    public function getFeeCommissionBytimeFilter($timeFilter) {
        $startEnd = Utils::getStartEndByTimeFilter($timeFilter, 'chart');

        $timeFilterOption = $this->getTimeFilterOption();
        $head = [
            'filter' => [
                'timeFilter' => [
                    'type' => 'select',
                    'option' => $timeFilterOption,
                    'value' => $timeFilter
                ],
            ]
        ];

        $chartValue = [];
        if($timeFilter == Consts::TIME_FILTER_TODAY) {
            $queryFee = CompleteTransaction::whereHas('user', function ($query) {
                    $query->where('type', '<>', Consts::USER_TYPE_BOT);
                })
                ->whereBetween('created_at', [$startEnd['start'], $startEnd['end']]);
            Utils::selectByTimeFilter($queryFee, $timeFilter, 'created_at', 'fee_usdt');
            $arrayFeeVal = $queryFee->first()->toArray();
            foreach ($arrayFeeVal as $key => $val) {
                $chartValue['fees'][] = $val ?? '0';
            }
            
            $queryCommission = ReferrerHistory::whereBetween('created_at', [$startEnd['start'], $startEnd['end']]);
            Utils::selectByTimeFilter($queryCommission, $timeFilter, 'created_at', 'usdt_value');
            $arrayCommissionVal = $queryCommission->first()->toArray();
            foreach ($arrayCommissionVal as $key => $val) {
                $chartValue['commissions'][] = $val ?? '0';
            }
        } 
        else {
            $query = ReportTransaction::whereHas('user', function ($query) {
                    $query->where('type', '<>', Consts::USER_TYPE_BOT);
                })
                ->whereBetween('date', [$startEnd['start'], $startEnd['end']]);
            $labels = Utils::selectByTimeFilter($query, $timeFilter, 'date', 'fee', 'fee');
            Utils::selectByTimeFilter($query, $timeFilter, 'date', 'commission', 'commission');
            $arrayVal = $query->first()->toArray();

            foreach ($arrayVal as $key => $val) {
                if(str_contains($key, 'fee')) {$chartValue['fees'][] = $val ?? '0';} 
                else {$chartValue['commissions'][] = $val ?? '0';}
            }
        } 

        if (!in_array($timeFilter, [Consts::TIME_FILTER_THIS_MONTH, Consts::TIME_FILTER_LAST_MONTH])) {
            $labels = [];
            foreach (Consts::CHART_COLUMN_LABEL[$timeFilter] as $label) {$labels[] = [$label];}
        }

        $data = [
            'labels' => $labels,
            'values' => $chartValue
        ];

        return [
            'head' => $head,
            'data' => $data
        ];
    }

    public function getFeeCommissionChart(Request $request) {
        $params = $request->all();
        $timeFilter = Arr::get($params, 'timeFilter', Consts::TIME_FILTER_THIS_YEAR);

        if (empty(Consts::TIME_FILTER_OPTION[$timeFilter])) {
            return response()->json([
                'success' => false,
                'message' => 'timeFilter not found',
            ], 404);
        }

        $result = $this->getFeeCommissionBytimeFilter($timeFilter);
        
        return response()->json([
            'success' => true,
            'message' => 'Success',
            'head' => $result['head'],
            'data' => $result['data']
        ]);
    }

    public function getProfitChart(Request $request) {
        $params = $request->all();
        $timeFilter = Arr::get($params, 'timeFilter', Consts::TIME_FILTER_THIS_YEAR);

        if (empty(Consts::TIME_FILTER_OPTION[$timeFilter])) {
            return response()->json([
                'success' => false,
                'message' => 'timeFilter not found',
            ], 404);
        }

        $result = $this->getFeeCommissionBytimeFilter($timeFilter);
        $labels = $result['data']['labels'];
        $fees = $result['data']['values']['fees'];
        $commissions = $result['data']['values']['commissions'];
        $values = [];
        foreach($fees as $index => $fee) {
            $values[] = BigNumber::new($fee)->sub($commissions[$index])->toString(); 
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Success',
            'head' => $result['head'],
            'data' => [
                'labels' =>$labels,
                'values' => $values
            ]
        ]);
    }
}
