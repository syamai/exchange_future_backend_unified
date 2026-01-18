<?php

namespace App\Http\Controllers\Partner;

use App\Consts;
use App\Http\Controllers\Controller;
use App\Models\AffiliateTrees;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class TradeController extends Controller
{
    public function getSpotQuery(Request $request) {
        $request->validate([
            'accountId' => 'required'
        ]);

        $params = $request->all();
        $accountId = Arr::get($params, 'accountId');
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $sort = Arr::get($params, 'sort', '');
        $direction = Arr::get($params, 'direction', Consts::SORT_ASC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $user = $request->user();

        $head = [
            'filter' => [
                'accountId' => $accountId,
                'sdate' => $sdate,
                'edate' => $edate
            ],
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit
        ];

        $isChild = AffiliateTrees::where('user_id', $accountId)
            ->where('referrer_id', $user->id)
            ->first();

        if(empty($isChild)) {
            return response()->json([
                'success' => false,
                'message' => 'User profile information not found',
            ], 404);
        }

        $items = Order::where('user_id', $accountId)
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                $query->whereBetween('created_at', [$sdate * 1000, $edate * 1000]);
            })
            ->selectRaw("id,
                user_id AS accountId,
                created_at AS createdTime,
                updated_at AS lastExecuted,
                status AS spotTradingStatus,
                CONCAT(coin, '/', currency) AS tradingPair,
                trade_type AS spotSide,
                executed_price AS avgPrice,
                executed_quantity AS filled,
                quantity AS qty,
                fee
                ")
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $_item = [
                'id' => $item->id,
                'accountId' => $item->accountId,
                'createdTime' => Carbon::createFromTimestampMs($item->createdTime)->toDateTimeString(),
                'lastExecuted' => Carbon::createFromTimestampMs($item->lastExecuted)->toDateTimeString(),
                'spotTradingStatus' => $item->spotTradingStatus,
                'tradingPair' => $item->tradingPair,
                'spotSide' => $item->spotSide,
                'avgPrice' => $item->avgPrice,
                'filled' => $item->filled,
                'qty' => $item->qty,
                'fee' => $item->fee
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
    public function getFuturesQuery(Request $request) {
        $request->validate([
            'accountId' => 'required'
        ]);

        $params = $request->all();
        $accountId = Arr::get($params, 'accountId');
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $currency = Arr::get($params, 'currency', '');
        $orderId = Arr::get($params, 'orderId', '');
        $sort = Arr::get($params, 'sort', '');
        $direction = Arr::get($params, 'direction', Consts::SORT_ASC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $user = $request->user();

        $isChild = AffiliateTrees::where('user_id', $accountId)
            ->where('referrer_id', $user->id)
            ->first();

        if(empty($isChild)) {
            return response()->json([
                'success' => false,
                'message' => 'User profile information not found',
            ], 404);
        }

        $queryParam = [
            'userId' => $accountId,
            'page' => $page,
            'pageSize' => $limit
        ];
        // $queryParam = '?userId=' . rawurlencode($userId) . '&page=' . rawurlencode($page) . '&pageSize=' . rawurlencode($limit);
        // $queryParam = $queryParam . '&sortBy=' . rawurlencode($sort) . '&isDesc=' . rawurlencode($isDesc);

        if ($sdate && $edate) {
            // $queryParam['startDate'] = Carbon::createFromTimestamp($sdate)->toDateString(); 
            // $queryParam['endDate'] = Carbon::createFromTimestamp($edate)->toDateString();

            $queryParam['startDate'] = $sdate;
            $queryParam['endDate'] = $edate;

        }

        if ($currency) {
            $queryParam['currency'] = $currency;
        }
        
        if ($orderId) {
            $queryParam['orderId'] = $orderId;
        }

        if ($sort) {
            if ($direction == Consts::SORT_ASC) {$isDesc = false;}
            else {$isDesc = true;}
            $queryParam['sortBy'] = $sort;
            $queryParam['isDesc'] = $isDesc;
        }
        
        // https://f-api.vh-mon.com/api/v1/trade/trade-history-for-partner?page=1&pageSize=20&startDate=2023-01-21&endDate=2025-02-21&userId=575&sortBy=id&isDesc=true&orderId=1&currency=USDT

        
        $futureBaseUrl = env('FUTURE_API_URL');
        $path = $futureBaseUrl . '/api/v1/trade/trade-history-for-partner';
        
        $response = Http::withBody(json_encode([
                'futureUser' => env('FUTURE_USER'),
                'futurePassword' => env('FUTURE_PASSWORD')
            ]), 'application/json')
            ->get($path, $queryParam);
        
        return $response->json();
    }
}
