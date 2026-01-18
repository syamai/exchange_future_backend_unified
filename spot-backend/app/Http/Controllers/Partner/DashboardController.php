<?php

namespace App\Http\Controllers\Partner;

use App\Consts;
use App\Http\Controllers\Controller;
use App\Models\AffiliateTrees;
use App\Models\Coin;
use App\Models\PartnerRequest;
use App\Models\ReferrerHistory;
use App\Models\User;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use DB;

class DashboardController extends Controller
{
    public function getTodayCommission (Request $request) {
        $params = $request->all();
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $user = $request->user();

        $head = [
            'page' => $page,
            'limit' => $limit
        ];

        $typeSpot = Consts::TYPE_EXCHANGE_BALANCE;
        $items = DB::table('referrer_histories', 'a')
            ->leftJoin('order_transactions AS b', 'a.order_transaction_id', 'b.id')
            ->whereBetween('a.created_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()])
            ->where('a.user_id', $user->id)
            ->groupBy('a.type', 'a.coin')
            ->selectRaw("SUM(a.amount) AS total_amount,
                a.coin,
                SUM(IF(a.type = '{$typeSpot}' AND a.coin <> 'USDT', b.price * a.amount, a.amount)) AS total_value,
                a.type")
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $_item = [
                'asset' => $item->coin,
                'amount' => $item->total_amount,
                'value' => $item->total_value,
                'commissionType' => $item->type,
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

    public function getRateCommission (Request $request) {
        $user = $request->user();

        $f1RateCommissionHighest = User::where('referrer_id', $user->id)
            ->join('user_rates AS b', 'users.id', '=', 'b.id')
            ->max('b.commission_rate');
        $data = [
            'rateCommission' => $user->userRate->commission_rate,
            'f1RateCommissionHighest' => $f1RateCommissionHighest,
        ];
        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data 
        ]);
    }

    public function getRateCommissionHistory(Request $request) {
        $user = $request->user();
        $data = [];
        $items = PartnerRequest::where([
                'user_id' => $user->id,
                'status' => Consts::PARTNER_REQUEST_APPROVED
            ])
            ->whereIn('type', [Consts::PARTNER_REQUEST_PARTNER_CHANGE_RATE, Consts::PARTNER_REQUEST_ADMIN_CHANGE_RATE])
            ->orderBy('updated_at', 'DESC')
            ->get()
            ->toArray();
        foreach ($items as $item) {
            $_item = [
                'accountId' => $item['user_id'],
                'perRate' => $item['old'],
                'newRate' => $item['new'],
                'changeBy' => $item['created_by'] ?? '',
                'description' => $item['reason'],
                'id' => $item['id'],
                'createdAt' => $item['created_at'],
                'updatedAt' => $item['updated_at'],
                'createdBy' => $item['created_by'],
                'updatedBy' => $item['processed_by']
            ];

            $data[] = $_item;
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data 
        ]);
    }

    public function getBalanceIssuanceHistory(Request $request) {
        $params = $request->all();
        $asset = Arr::get($params, 'asset', '');
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $user = $request->user();

        $assets = ReferrerHistory::where('user_id', $user->id)
            ->groupBy('coin')
            ->select('coin')
            ->pluck('coin')
            ->all();

        $assetImage = Coin::whereIn('coin', $assets)
            ->pluck('icon_image', 'coin')
            ->all();

        $assetOption = [];
        foreach ($assets as $assetItem) {
            $assetOption[] = [
                'label' => $assetItem,
                'value' => $assetItem,
                'iconImage' => $assetImage[strtolower($assetItem)] ?? ''
            ];
        }

        $head = [
            'filter' => [
                'asset' => [
                    'type' => 'select',
                    'option' => $assetOption,
                    'value' => $asset
                ],
                'sdate' => $sdate,
                'edate' => $edate,
            ],
            'page' => $page,
            'limit' => $limit
        ];
        
        $asset = strtoupper($asset);

        $items = ReferrerHistory::where('user_id', $user->id)
            ->when($asset, function ($query, $asset) {
                $query->where('coin', $asset);
            })
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                $query->whereBetween('created_at', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);

            })
            ->orderBy('created_at', 'DESC')
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            
            $_item = [
                'accountId' => $item->user_id,
                'asset' => $item->coin,
                'amount' => $item->amount,
                'value' => $item->usdt_value,
                'completedDate' => Carbon::parse($item->created_at)->format('Y-m-d'),
                'commissionType' => $item->type,
                'id' => $item->id,
                'createdAt' => $item->created_at,
                'updatedAt' => $item->updated_at,
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

    public function getTodayTrade(Request $request) {
        $user = $request->user();
        $now = Carbon::now();
        $sdate = $now->copy()->startOfDay()->format('Y-m-d H:i:s');
        $edate = $now->copy()->endOfDay()->format('Y-m-d H:i:s');

        $countRefNewInToday = DB::table('affiliate_trees')
    ->whereBetween('created_at', [$sdate, $edate])
    ->where('referrer_id', $user->id)
    ->selectRaw("
        COUNT(DISTINCT IF(level = 1, user_id, NULL)) AS direct_referral_register_today,
        COUNT(DISTINCT IF(level <> 1, user_id, NULL)) AS indirect_referral_register_today
    ")->first();

$directReferralToday = collect($countRefNewInToday)->get('direct_referral_register_today', 0);
$indirectReferralToday = collect($countRefNewInToday)->get('indirect_referral_register_today', 0);

        $subQuery = "SELECT SUM(rh.usdt_value)
            FROM referrer_histories AS rh
            WHERE rh.user_id = {$user->id}
                AND rh.transaction_owner = a.user_id
                AND rh.created_at BETWEEN '{$sdate}' AND '{$edate}'";

        $total = DB::table('affiliate_trees', 'a')
            ->leftJoin('report_transactions AS b', 'a.user_id', 'b.user_id')
            ->where('a.referrer_id', $user->id)
            ->where('b.date', $now->copy()->format('Y-m-d'))
            ->selectRaw("COUNT(DISTINCT(IF(a.level = 1, b.user_id, null))) AS direct_referral,
                COUNT(DISTINCT(IF(a.level <> 1, b.user_id, null))) AS indirect_referral,
                SUM(IF(a.level = 1, b.volume, 0)) AS direct_volume,
                SUM(IF(a.level <> 1, b.volume, 0)) AS indirect_volume,
                SUM(IF(a.level = 1, b.fee, 0)) AS direct_fee,
                SUM(IF(a.level <> 1, b.fee, 0)) AS indirect_fee,
                SUM(IF(a.level = 1, ({$subQuery}), 0)) AS direct_commission,
                SUM(IF(a.level <> 1, ({$subQuery}), 0)) AS indirect_commission
                ")
            ->first();
        
        $data = [
            [
                'referral' => $directReferralToday, //(string) $total->direct_referral ?: '0',
                'totalVolumeValue' => (string) $total->direct_volume ?: '0',
                'totalFeeValue' => (string) $total->direct_fee ?: '0',
                'refType' => 1, 
                'commission' => (string) $total->direct_commission ?: '0'
            ],
            [
                'referral' => $indirectReferralToday, //(string) $total->indirect_referral ?: '0',
                'totalVolumeValue' => (string) $total->indirect_volume ?: '0',
                'totalFeeValue' => (string) $total->indirect_fee ?: '0',
                'refType' => 2, 
                'commission' => (string) $total->indirect_commission ?: '0' 
            ],
            [
                'referral' => $directReferralToday + $indirectReferralToday, //BigNumber::new($total->direct_referral)->add($total->indirect_referral)->toString(),
                'totalVolumeValue' => BigNumber::new($total->direct_volume)->add($total->indirect_volume)->toString(),
                'totalFeeValue' => BigNumber::new($total->direct_fee)->add($total->indirect_fee)->toString(),
                'refType' => 0, 
                'commission' => BigNumber::new($total->direct_commission)->add($total->indirect_commission)->toString() 
            ],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'head' => [],
            'list' => [
                'data' => $data
            ]
        ]);
    }

    public function getTradeHistory(Request $request) {
        $params = $request->all();
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $isDirectRef = Arr::get($params, 'isDirectRef');
        $fromAccountId = Arr::get($params, 'fromAccountId', '');
        $sort = Arr::get($params, 'sort', 'b.executed_date');
        $direction = Arr::get($params, 'direction', Consts::SORT_ASC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $user = $request->user();

        foreach(Consts::IS_DIRECT_REF_LABEL as $k => $v) {
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

        $directIds = AffiliateTrees::where('referrer_id', $user->id)
            ->where('level', 1)
            ->pluck('user_id')
            ->all();
        $listDirect = implode(',', $directIds);

        $typeBuy = Consts::ORDER_SIDE_BUY;

        $items = DB::table('referrer_histories', 'a')
            ->join('complete_transactions AS b', 'a.complete_transaction_id', 'b.id')
            ->where('a.user_id', $user->id)
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                $query->whereBetween('b.executed_date', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
    
            })
            ->when(!is_null($isDirectRef), function ($query, $check) use ($isDirectRef, $directIds) {
                if ($isDirectRef == 1) {$query->whereIn('b.user_id', $directIds);}
                else {$query->whereNotIn('b.user_id', $directIds);}
            })
            ->when($fromAccountId, function ($query, $fromAccountId) {
                $query->where('b.user_id', $fromAccountId);
            })
            ->groupBy('accountId', 'completedDate', 'asset')
            ->orderBy($sort, $direction)
            ->selectRaw("a.transaction_owner AS accountId,
                a.coin AS asset,
                SUM(a.amount) AS totalAmount,
                SUM(a.usdt_value) AS totalAmountValue,
                SUM(IF(b.transaction_type = '{$typeBuy}', b.quantity, b.amount)) AS totalVolume,
                SUM(b.amount_usdt) AS totalVolumeValue,
                IF(b.user_id IN ({$listDirect}), 1, 0) AS isDirectReferral,
                b.executed_date AS completedDate,
                SUM(b.fee) AS totalFeeVolume
            ")
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $_item = [
                'accountId' => $item->accountId,
                'asset' => $item->asset,
                'totalAmount' => $item->totalAmount,
                'totalAmountValue' => $item->totalAmountValue,
                'totalVolume' => $item->totalVolume,
                'totalVolumeValue' => $item->totalVolumeValue,
                'isDirectReferral' => $item->isDirectReferral,
                'completedDate' => $item->completedDate,
                'totalFeeVolume' => $item->totalFeeVolume,
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
}
