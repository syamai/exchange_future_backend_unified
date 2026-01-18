<?php

namespace App\Http\Controllers\Partner;

use App\Consts;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Services\ReferralService;
use App\Models\AffiliateTrees;
use App\Models\PartnerRequest;
use Illuminate\Support\Arr;
use DB;

class PartnerController extends Controller
{
    public $referralService;
    public function __construct(ReferralService $referralService){
        $this->referralService = $referralService;
    }
    public function getEstimateSettingCommission($id, Request $request) {
        $user = $request->user();
        $isChild = AffiliateTrees::where('user_id', $id)
            ->where('referrer_id', $user->id)
            ->first();

        if(empty($isChild)) {
            return response()->json([
                'success' => false,
                'message' => 'User profile information not found',
            ], 404);
        }
        $child = $isChild->userDown;
        if (is_null($child->is_partner)) {
            return response()->json([
                'success' => false,
                'message' => 'This user is not partner',
            ], 400);
        }

        $range = $this->referralService->getRangeSettingCommissionById($child);

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $range
        ]);
    }

    public function setCommission(Request $request) {
        $request->validate([
            'accountId' => 'required',
            'rateCommission' => 'required|numeric',
        ]);
        $params = $request->all();
        $accountId = Arr::get($params, 'accountId');
        $rateCommission = Arr::get($params, 'rateCommission');
        
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

        if($isChild->level <> 1) {
            return response()->json([
                'success' => false,
                'message' => 'User is not direct user',
            ], 400); 
        }

        $child = $isChild->userDown;
        if (is_null($child->is_partner)) {
            return response()->json([
                'success' => false,
                'message' => 'This user is not partner',
            ], 400);
        }

        if($user->userRate->commission_rate < 40) {
            return response()->json([
                'success' => false,
                'message' => 'Current ratio must be greater than or equal to 40',
            ], 400); 
        }

        $checkRate = $this->referralService->checkCommissionRate($child, $rateCommission);
        if(!$checkRate['success']) {
            return response()->json($checkRate, 200);
        }

        if ($child->userRate->commission_rate == $rateCommission) {
            return response()->json([
                'success' => false,
                'message' => "Rate must be different from current rate",
            ], 400);
        }

        $checkIssetRequest = PartnerRequest::where('user_id', $child->id)
            ->where('type', Consts::PARTNER_REQUEST_PARTNER_CHANGE_RATE)
            ->where('status', Consts::PARTNER_REQUEST_PENDING)
            ->first();
        if (!empty($checkIssetRequest)) {
            return response()->json([
                'success' => false,
                'message' => "Account {$child->id} has a request to change rate commission that has not been processed yet, please wait",
            ], 202);
        }

        PartnerRequest::create([
            'user_id' => $child->id,
            'type' => Consts::PARTNER_REQUEST_PARTNER_CHANGE_RATE,
            'detail' => Consts::PARTNER_REQUEST_DETAIL[Consts::PARTNER_REQUEST_PARTNER_CHANGE_RATE],
            'old' => $child->userRate->commission_rate,
            'new' => $rateCommission,
            'status' => Consts::PARTNER_REQUEST_PENDING,
            'created_by' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Success',
        ]);
    }

    public function getPartnerData(Request $request) {
        $params = $request->all();
        $invitedById = Arr::get($params, 'invitedById', '');
        $phoneNumber = Arr::get($params, 'phoneNumber', '');
        $isDirectRef = Arr::get($params, 'isDirectRef');
        $accountId = Arr::get($params, 'accountId', '');
        $email = Arr::get($params, 'email', '');
        $sort = Arr::get($params, 'sort', 'accountId');
        $direction = Arr::get($params, 'direction', Consts::SORT_ASC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $user = $request->user();
        $isDirectRefOption = [];
        foreach(Consts::IS_DIRECT_REF_LABEL as $k => $v) {
            $isDirectRefOption[] = [
                'label' => $v,
                'value' => $k
            ];
        }
        $head = [
            'filter' => [
                'invitedById' => $invitedById,
                'phoneNumber' => $phoneNumber,
                'isDirectRef' => [
                    'type' => 'select',
                    'option' => $isDirectRefOption,
                    'value' => $isDirectRef
                ],
                'accountId' => $accountId,
                'email' => $email
            ],
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit
        ];

        $items = DB::table('affiliate_trees', 'a')
            ->join('users AS b', 'a.user_id', 'b.id')
            ->join('users AS c', 'b.referrer_id', 'c.id')
            ->join('user_rates AS d', 'd.id', 'b.id')
            ->join('report_transactions AS e', 'b.id', 'e.user_id')
            ->where('a.referrer_id', $user->id)
            ->where('b.is_partner', Consts::PARTNER_ACTIVE)
            ->where('e.volume', '>', 0)
            ->when($invitedById, function ($query, $invitedById) {
                $query->where('c.id', $invitedById);
            })
            ->when($phoneNumber, function ($query, $phoneNumber) {
                $query->where('b.phone_no', $phoneNumber);
            })
            ->when(!is_null($isDirectRef), function ($query, $check) use ($isDirectRef) {
                if($isDirectRef == 1) {$query->where('a.level', 1);}
                else {$query->where('a.level', '<>', 1);}
            })
            ->when($accountId, function ($query, $accountId) {
                $query->where('b.id', $accountId);
            })
            ->when($email, function ($query, $email) {
                $query->where('b.email', $email);
            })
            ->groupBy('b.id')
            ->orderBy($sort, $direction)
            ->selectRaw("b.id AS accountId,
                (
                    SELECT SUM(rh.usdt_value)
                    FROM referrer_histories AS rh
                    WHERE rh.user_id = {$user->id}
                        AND rh.transaction_owner = d.id
                ) AS yourCommission,
                SUM(e.volume) AS totalTradingVolumeValue,
                SUM(e.fee) AS totalFeeValue,
                IF(a.level = 1, 1, 0) AS isDirectReferral,
                d.commission_rate AS rateCommission,
                b.email,
                b.name,
                b.referrer_code AS inviteCode,
                c.id AS invitedById,
                c.email AS invitedByEmail,
                b.is_partner AS isPartner,
                b.created_at AS createdAt
                ")
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $_item = [
                'accountId' => $item->accountId,
                'yourCommission' => $item->yourCommission,
                'totalTradingVolumeValue' => $item->totalTradingVolumeValue,
                'totalFeeValue' => $item->totalFeeValue,
                'isDirectReferral' => $item->isDirectReferral,
                'rateCommission' => $item->rateCommission,
                'email' => $item->email,
                'name' => $item->name,
                'inviteCode' => $item->inviteCode,
                'invitedById' => $item->invitedById,
                'invitedByEmail' => $item->invitedByEmail,
                'isPartner' => $item->isPartner,
                'createdAt' => $item->createdAt,
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
