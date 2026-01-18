<?php

namespace App\Http\Controllers\PartnerAdmin;

use App\Consts;
use App\Http\Controllers\Controller;
use App\Models\CalculateProfit;
use App\Models\Coin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ProfitController extends Controller
{
    public function getStatisticsByDate(Request $request) {
        $params = $request->all();
        $coin = Arr::get($params, 'coin', '');
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $sort = Arr::get($params, 'sort', 'date');
        $direction = Arr::get($params, 'direction', Consts::SORT_DESC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $coins = Coin::all()->pluck('coin')->all();
        $coinOption = [];
        foreach($coins as $coinItem) {
            $coinOption[] = [
                'label' => strtoupper($coinItem),
                'value' => $coinItem,
            ];
        }

        $head = [
            'filter' => [
                'coin' => [
                    'type' => 'select',
                    'option' => $coinOption,
                    'value' => $coin
                    ],
                'sdate' => $sdate,
                'edate' => $edate,
            ],
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit
        ];

        $items = CalculateProfit::when($coin, function ($query, $coin) {
                $query->where('coin', $coin);
            })
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                $query->whereBetween('date', [$sdate, $edate]);
            })
            ->orderBy($sort, $direction)
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $_item = [
                'id' => $item->id, 
                'date' => $item->date, 
                'coin' => strtoupper($item->coin), 
                'receive_fee' => $item->receive_fee, 
                'referral_fee' => $item->referral_fee, 
                'net_fee' => $item->net_fee
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
