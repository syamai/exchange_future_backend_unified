<?php

namespace App\Http\Controllers\PartnerAdmin;

use App\Consts;
use App\Http\Controllers\Controller;
use App\Models\ReferrerHistory;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ReferralController extends Controller
{
    public function getDistributions(Request $request) {
        $params = $request->all();
        $accountId = Arr::get($params, 'accountId', '');
        $isDirectRef = Arr::get($params, 'isDirectRef');
        $fromAccountId = Arr::get($params, 'fromAccountId', '');
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $sort = Arr::get($params, 'sort', 'created_at');
        $direction = Arr::get($params, 'direction', Consts::SORT_DESC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        foreach (Consts::IS_DIRECT_REF_LABEL as $k => $v) {
            $isDirectRefOption[] = [
                'label' => $v,
                'value' => $k
            ];
        }
        
        $head = [
            'filter' => [
                'accountId' => $accountId,
                'isDirectRef' => [
                    'type' => 'select',
                    'option' => $isDirectRefOption,
                    'value' => $isDirectRef ?? ''
                ],
                'fromAccountId' => $fromAccountId,
            ],
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit
        ];

        switch ($sort) {
            case 'accountId':
                $sort = 'user_id';
                break;
            case 'fromAccountId':
                $sort = 'transaction_owner';
                break;
            case 'amount':
                break;
            case 'asset':
                $sort = 'coin';
                break;
            default:
            $sort = 'id';
        }
        
        $items = ReferrerHistory::with('completeTransaction')
            ->when($accountId, function ($query, $accountId) {
                $query->where('user_id', $accountId);
            })
            ->when(!is_null($isDirectRef), function ($query, $check) use ($isDirectRef) {
                $query->where('is_direct_ref', $isDirectRef);
            })
            ->when($fromAccountId, function ($query, $fromAccountId) {
                $query->where('transaction_owner', $fromAccountId);
            })
            ->when(($sdate && $edate), function ($query, $check) use ($sdate, $edate) {
                $query->whereBetween('created_at', [Carbon::createFromTimestamp($sdate), Carbon::createFromTimestamp($edate)]);
            })
            ->orderBy($sort, $direction)
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $tradingVolume = $item->completeTransaction->transaction_type == Consts::ORDER_SIDE_BUY ?  $item->completeTransaction->quantity : $item->completeTransaction->amount;

            $_item = [
                'tradeType' => $item->completeTransaction->transaction_type,
                'id' => $item->id,
                'accountId' => $item->user_id,
                'fromAccountId' => $item->transaction_owner,
                'amount' => $item->amount,
                'asset' => $item->coin,
                'commissionType' => $item->type,
                'completedDate' => Carbon::parse($item->created_at)->format('Y-m-d H:i:s'),
                'createdAt' => $item->created_at,
                'email' => $item->email,
                'fee' => $item->completeTransaction->fee,
                'feeValue' => $item->completeTransaction->fee_usdt,
                'isDirectReferral' => $item->is_direct_ref,
                'isIssuance' => '',
                'rateCommission' => $item->commission_rate,
                'rateOffsetTrading' => '',
                'tradingVolume' => $tradingVolume,
                'tradingVolumeValue' => $item->completeTransaction->amount_usdt,
                'updatedAt' => $item->updated_at,
                'value' => $item->usdt_value,
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
