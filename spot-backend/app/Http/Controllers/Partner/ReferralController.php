<?php

namespace App\Http\Controllers\Partner;

use App\Consts;
use App\Http\Controllers\Controller;
use App\Models\AffiliateTrees;
use App\Models\ReportTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use DB;

class ReferralController extends Controller
{
    public function getRefStatistic(Request $request)
    {
        $params = $request->all();
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $sort = Arr::get($params, 'sort', 'id');
        $direction = Arr::get($params, 'direction', Consts::SORT_ASC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $user = $request->user();
        if ($sort == 'all') {
            $sort = 'total';
        }

        $head = [
            'filter' => [
                'sdate' => $sdate,
                'edate' => $edate
            ],
            'page' => $page,
            'limit' => $limit
        ];

        $items = AffiliateTrees::where('referrer_id', $user->id)
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                $query->whereBetween('created_at', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
            })
            ->groupBy('date')
            ->selectRaw("id,
                DATE(created_at) AS date,
                COUNT(id) AS total,
                SUM(IF(level = 1, 1, 0)) AS totalDirectReferral,
                SUM(IF(level <> 1, 1, 0)) AS totalInDirectReferral
                ")
            ->orderBy($sort, $direction)
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $_item = [
                'date' => $item->date,
                'all' => (string) $item->total,
                'totalDirectReferral' => $item->totalDirectReferral,
                'totalInDirectReferral' => $item->totalInDirectReferral
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

    public function getTradeCommissionOverview(Request $request)
    {

        $params = $request->all();
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $sort = Arr::get($params, 'sort', 'date');
        $direction = Arr::get($params, 'direction', Consts::SORT_DESC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $user = $request->user();

        $head = [
            'filter' => [
                'sdate' => $sdate,
                'edate' => $edate
            ],
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit,
        ];

        $child = AffiliateTrees::where('referrer_id', $user->id)->pluck('user_id')->all();
        $listChild = implode(',', $child);

        $listUserId = array_merge($child, [$user->id]);
        $items = ReportTransaction::whereIn('user_id', $listUserId)
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                $query->whereBetween('date', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
            })
            ->groupBy('date')
            ->orderBy($sort, $direction)
            ->selectRaw("date,
                SUM(IF(user_id IN ({$listChild}), volume, 0)) AS totalTradingVolumeValue,
                COUNT(IF(user_id IN ({$listChild}) AND volume > 0, user_id, null)) AS totalTraders,
                SUM(IF(user_id IN ({$listChild}), fee, 0)) AS totalFeeValue,
                SUM(IF(user_id = {$user->id}, commission, 0)) AS yourCommission
                ")
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $_item = [
                'date' => $item->date,
                'totalTradingVolumeValue' => (string) $item->totalTradingVolumeValue,
                'totalTraders' => (string) $item->totalTraders,
                'totalFeeValue' => (string) $item->totalFeeValue,
                'yourCommission' => (string) $item->yourCommission,
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

    public function getTradeCommissionVolume(Request $request)
    {
        $params = $request->all();
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $sort = Arr::get($params, 'sort', 'date');
        $direction = Arr::get($params, 'direction', Consts::SORT_DESC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $user = $request->user();

        if ($sort == 'all') {
            $sort = 'total';
        }

        $head = [
            'filter' => [
                'sdate' => $sdate,
                'edate' => $edate
            ],
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit,
        ];

        $items = DB::table('affiliate_trees', 'a')
            ->join('report_transactions AS b', 'a.user_id', 'b.user_id')
            ->where('a.referrer_id', $user->id)
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                $query->whereBetween('b.date', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
            })
            ->groupBy('b.date')
            ->orderBy($sort, $direction)
            ->selectRaw("b.date,
                SUM(b.volume) AS total,
                SUM(IF(a.level = 1, b.volume, 0)) AS totalDirectReferral,
                SUM(IF(a.level <> 1, b.volume, 0)) AS totalInDirectReferral
                ")
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $_item = [
                'date' => $item->date,
                'all' => (string) $item->total,
                'totalDirectReferral' => (string) $item->totalDirectReferral,
                'totalInDirectReferral' => (string) $item->totalInDirectReferral,
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

    public function getTradeCommissionTraders(Request $request)
    {
        $params = $request->all();
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $sort = Arr::get($params, 'sort', 'date');
        $direction = Arr::get($params, 'direction', Consts::SORT_DESC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $user = $request->user();

        if ($sort == 'all') {
            $sort = 'total';
        }

        $head = [
            'filter' => [
                'sdate' => $sdate,
                'edate' => $edate
            ],
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit,
        ];

        $items = DB::table('affiliate_trees', 'a')
            ->join('report_transactions AS b', 'a.user_id', 'b.user_id')
            ->where('a.referrer_id', $user->id)
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                $query->whereBetween('b.date', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
            })
            ->groupBy('b.date')
            ->orderBy($sort, $direction)
            ->selectRaw("b.date,
                COUNT(b.user_id) AS total,
                COUNT(IF(a.level = 1, b.user_id, null)) AS totalDirectReferral,
                COUNT(IF(a.level <> 1, b.user_id, null)) AS totalInDirectReferral
                ")
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $_item = [
                'date' => $item->date,
                'all' => (string) $item->total,
                'totalDirectReferral' => (string) $item->totalDirectReferral,
                'totalInDirectReferral' => (string) $item->totalInDirectReferral,
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

    public function getTradeCommissionByCommission(Request $request)
    {
        $params = $request->all();
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $sort = Arr::get($params, 'sort', 'date');
        $direction = Arr::get($params, 'direction', Consts::SORT_DESC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $user = $request->user();

        if ($sort == 'all') {
            $sort = 'total';
        }

        $head = [
            'filter' => [
                'sdate' => $sdate,
                'edate' => $edate
            ],
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit,
        ];

        $items = ReportTransaction::where('user_id', $user->id)
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                $query->whereBetween('date', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
            })
            ->orderBy($sort, $direction)
            ->selectRaw("date,
                commission AS total,
                direct_commission AS totalDirectReferral,
                indirect_commission AS totalInDirectReferral
                ")
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $_item = [
                'date' => $item->date,
                'all' => $item->total,
                'totalDirectReferral' => $item->totalDirectReferral,
                'totalInDirectReferral' => $item->totalInDirectReferral,
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

    public function getUserQuery(Request $request)
    {
        $params = $request->all();
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $isDirectRef = Arr::get($params, 'isDirectRef');
        $fromAccountId = Arr::get($params, 'fromAccountId', '');
        $sort = Arr::get($params, 'sort', '');
        $direction = Arr::get($params, 'direction', Consts::SORT_ASC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $user = $request->user();
        $isDirectRefOption = [];
        foreach (Consts::IS_DIRECT_REF_LABEL as $k => $v) {
            $isDirectRefOption[] = [
                'label' => $v,
                'value' => $k
            ];
        }
        $head = [
            'filter' => [
                'sdate' => $sdate,
                'edate' => $edate
            ],
            'isDirectRef' => [
                'type' => 'select',
                'option' => $isDirectRefOption,
                'value' => $isDirectRef
            ],
            'fromAccountId' => $fromAccountId,
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit
        ];

        $items = DB::table('affiliate_trees AS a')
            ->join('report_transactions AS b', 'a.user_id', '=', 'b.user_id')
            ->join('user_rates AS c', 'a.user_id', '=', 'c.id')
            ->where('a.referrer_id', $user->id)
            ->where('b.volume', '>', 0)
            ->when($sdate && $edate, function ($query) use ($sdate, $edate) {
                $query->whereBetween('b.date', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
            })
            ->when(!is_null($isDirectRef), function ($query) use ($isDirectRef) {
                $query->where('a.level', $isDirectRef == 1 ? 1 : '<>', 1);
            })
            ->when($fromAccountId, function ($query, $fromAccountId) {
                $query->where('b.user_id', $fromAccountId);
            })
            ->groupBy('a.user_id')
            ->when($sort, function ($query) use ($sort, $direction) {
                $query->orderBy($sort, $direction);
            })
            ->selectRaw("
            a.user_id AS fromAccountId,
            IF(a.level = 1, 1, 0) AS isDirectReferral,
            c.commission_rate AS rateCommission,
            SUM(b.volume) AS totalTradingVolumeValue,
            SUM(b.fee) AS totalFeeValue
        ")
            ->paginate($limit);

        
        $refs = $items->getCollection()->pluck('fromAccountId')->toArray();
        $commissionList = $this->getReferralEarnings($user->id, $refs)
            ->pluck('commission', 'transaction_owner')
            ->toArray();

        $items->setCollection($items->getCollection()->map(function ($item) use ($commissionList) {
            $transactionOwner = $item->fromAccountId;
            $commission = $commissionList[$transactionOwner] ?? 0;

            return [
                'fromAccountId' => $item->fromAccountId,
                'isDirectReferral' => $item->isDirectReferral,
                'rateCommission' => $item->rateCommission,
                'totalTradingVolumeValue' => $item->totalTradingVolumeValue,
                'totalFeeValue' => $item->totalFeeValue,
                'commission' => $commission
            ];
        }));
        
        return response()->json([
            'success' => true,
            'message' => 'Success',
            'head' => $head,
            'list' => $items->appends($params)
        ]);
    }

    public function getUserQueryDetail(Request $request)
    {
        $params = $request->all();
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $isDirectRef = Arr::get($params, 'isDirectRef');
        $fromAccountId = Arr::get($params, 'fromAccountId', '');
        $sort = Arr::get($params, 'sort', '');
        $direction = Arr::get($params, 'direction', Consts::SORT_ASC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $user = $request->user();
        $isDirectRefOption = [];
        foreach (Consts::IS_DIRECT_REF_LABEL as $k => $v) {
            $isDirectRefOption[] = [
                'label' => $v,
                'value' => $k
            ];
        }
        $head = [
            'filter' => [
                'sdate' => $sdate,
                'edate' => $edate
            ],
            'isDirectRef' => [
                'type' => 'select',
                'option' => $isDirectRefOption,
                'value' => $isDirectRef
            ],
            'fromAccountId' => $fromAccountId,
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit
        ];

        $items = DB::table('affiliate_trees', 'a')
            ->join('report_transactions AS b', 'a.user_id', 'b.user_id')
            ->where('a.referrer_id', $user->id)
            ->where('b.volume', '>', 0)
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                $query->whereBetween('b.date', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
            })
            ->when(!is_null($isDirectRef), function ($query, $check) use ($isDirectRef) {
                if ($isDirectRef == 1) {
                    $query->where('a.level', 1);
                } else {
                    $query->where('a.level', '<>', 1);
                }
            })
            ->when($fromAccountId, function ($query, $fromAccountId) {
                $query->where('b.user_id', $fromAccountId);
            })
            ->when($sort, function ($query, $sort) use ($direction) {
                $query->orderBy($sort, $direction);
            })
            ->selectRaw("b.user_id AS fromAccountId,
                IF(a.level = 1, 1, 0) AS isDirectReferral,
                b.volume AS totalTradingVolumeValue,
                b.fee AS totalFeeValue,
                date AS completedDate
                ")
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $_item = [
                'fromAccountId' => $item->fromAccountId,
                'isDirectReferral' => $item->isDirectReferral,
                'totalTradingVolumeValue' => $item->totalTradingVolumeValue,
                'totalFeeValue' => $item->totalFeeValue,
                'completedDate' => $item->completedDate,
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

    public function getUserQueryTop10(Request $request)
    {
        $params = $request->all();
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $limit = Arr::get($params, 'limit', 10);

        $user = $request->user();

        $head = [
            'filter' => [
                'sdate' => $sdate,
                'edate' => $edate
            ],
        ];

        $data = [];

        $items = DB::table('referrer_histories', 'a')
            ->join('complete_transactions AS b', 'a.complete_transaction_id', 'b.id')
            ->where('a.user_id', $user->id)
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                $query->whereBetween('a.created_at', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
            })
            ->groupBy('a.transaction_owner')
            ->orderByDesc('yourCommission')
            ->selectRaw("a.transaction_owner AS fromAccountId,
                SUM(b.amount_usdt) AS totalTradingVolumeValue,
                SUM(b.fee_usdt) AS totalFeeValue,
                SUM(a.usdt_value) AS yourCommission
                ")
            ->limit($limit)
            ->get();

        foreach ($items as $item) {
            $data[] = [
                'fromAccountId' => $item->fromAccountId,
                'totalTradingVolumeValue' => $item->totalTradingVolumeValue,
                'totalFeeValue' => $item->totalFeeValue,
                'yourCommission' => $item->yourCommission,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'head' => $head,
            'list' => [
                'data' => $data
            ]
        ]);
    }

    public function getReferralEarnings($userId, $refs)
    {
        if (empty($refs)) {
            return collect([]);
        }

        return DB::table('referrer_histories')
            ->where('user_id', $userId)
            ->whereIn('transaction_owner', $refs)
            ->selectRaw('SUM(usdt_value) as commission, transaction_owner')
            ->groupBy('transaction_owner')
            ->get();
    }


    public function getTradeVolumeDirect(Request $request)
    {
        $params = $request->all();
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $sdateCarbon = Carbon::createFromTimestamp($sdate);
        $edateCarbon = Carbon::createFromTimestamp($edate);

        $user = $request->user();

        // Subquery 1: Commission
        $commissionSub = DB::table('report_transactions')
            ->selectRaw('
                date,
                SUM(direct_commission) as commission
            ')
            ->where('user_id', $user->id)
            ->when(($sdate && $edate), function ($query) use ($sdateCarbon, $edateCarbon) {
                $query->whereBetween('date', [$sdateCarbon, $edateCarbon]);
            })
            ->groupBy('date');

        // Subquery 2: Volume & Traders
        $volumeSub = DB::table('affiliate_trees as a')
            ->join('report_transactions as b', 'a.user_id', '=', 'b.user_id')
            ->selectRaw('
                b.date,
                SUM(IF(a.level = 1, b.volume, 0)) AS tradingVolume,
                COUNT(IF(a.level = 1, b.user_id, NULL)) AS traders
            ')
            ->where('a.referrer_id', $user->id)
            ->when(($sdate && $edate), function ($query) use ($sdateCarbon, $edateCarbon) {
                $query->whereBetween('b.date', [$sdateCarbon, $edateCarbon]);
            })
            ->groupBy('b.date');

        // Join 2 subquery lại
        $items = DB::table(DB::raw("({$commissionSub->toSql()}) as c"))
            ->mergeBindings($commissionSub) // merge parameters
            ->leftJoin(DB::raw("({$volumeSub->toSql()}) as v"), 'c.date', '=', 'v.date')
            ->mergeBindings($volumeSub)
            ->select(
                'c.date',
                'v.tradingVolume',
                'v.traders',
                'c.commission'
            )
            ->orderBy('c.date', 'desc')
            ->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'list' => $items->appends($params)
        ]);
    }

    public function getTradeVolumeInDirect(Request $request)
    {
        $params = $request->all();
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $sdateCarbon = Carbon::createFromTimestamp($sdate);
        $edateCarbon = Carbon::createFromTimestamp($edate);

        $user = $request->user();

        // Subquery 1: Commission
        $commissionSub = DB::table('report_transactions')
            ->selectRaw('
                date,
                SUM(indirect_commission) as commission
            ')
            ->where('user_id', $user->id)
            ->when(($sdate && $edate), function ($query) use ($sdateCarbon, $edateCarbon) {
                $query->whereBetween('date', [$sdateCarbon, $edateCarbon]);
            })
            ->groupBy('date');

        // Subquery 2: Volume & Traders
        $volumeSub = DB::table('affiliate_trees as a')
            ->join('report_transactions as b', 'a.user_id', '=', 'b.user_id')
            ->selectRaw('
                b.date,
                SUM(IF(a.level <> 1, b.volume, 0)) AS tradingVolume,
                COUNT(IF(a.level <> 1, b.user_id, null)) traders
            ')
            ->where('a.referrer_id', $user->id)
            ->when(($sdate && $edate), function ($query) use ($sdateCarbon, $edateCarbon) {
                $query->whereBetween('b.date', [$sdateCarbon, $edateCarbon]);
            })
            ->groupBy('b.date');

        // Join 2 subquery lại
        $items = DB::table(DB::raw("({$commissionSub->toSql()}) as c"))
            ->mergeBindings($commissionSub) // merge parameters
            ->leftJoin(DB::raw("({$volumeSub->toSql()}) as v"), 'c.date', '=', 'v.date')
            ->mergeBindings($volumeSub)
            ->select(
                'c.date',
                'v.tradingVolume',
                'v.traders',
                'c.commission'
            )
            ->orderBy('c.date', 'desc')
            ->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'list' => $items->appends($params)
        ]);
    }
}
