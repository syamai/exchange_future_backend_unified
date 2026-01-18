<?php

namespace App\Http\Controllers\PartnerAdmin;

use App\Consts;
use App\Http\Controllers\Controller;
use App\Http\Services\ActivityHistoryService;
use App\Http\Services\BalanceService;
use App\Http\Services\PriceService;
use App\Http\Services\ReferralService;
use App\Models\AffiliateTrees;
use App\Models\CoinsConfirmation;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Models\UserRates;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PartnerController extends Controller
{
    private $referralService, $priceService, $balanceService, $activityHistoryService;

    public function __construct(ReferralService $referralService, PriceService $priceService, BalanceService $balanceService, ActivityHistoryService $activityHistoryService)
    {
        $this->referralService = $referralService;
        $this->priceService = $priceService;
        $this->balanceService = $balanceService;
        $this->activityHistoryService = $activityHistoryService;
    }

    public function getAffiliateProfile($uid, Request $request)
    {
        $profile = User::where('uid', $uid)->first();
        if (empty($profile)) {
            $res = [
                'success' => false,
                'message' => 'Account profile information not found',
            ];
            return response()->json($res, 404);
        }
        $data = [
            'id' => $profile->id,
            'uid' => $profile->uid,
            'email' => $profile->email
        ];
        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $data
        ]);
    }

    public function create(Request $request)
    {
        $messages = [
            'id.required' => 'The user UID is required.',
            'id.exists' => 'The user UID does not exist in our records.',
            'commissionRate.required' => 'The commission rate is required.',
            'commissionRate.numeric' => 'The commission rate must be a number.',
        ];

        $request->validate([
            'id' => 'required|exists:users,uid',
            'commissionRate' => 'required|numeric',
        ], $messages);

        $uid = $request->id;
        $commissionRate = $request->commissionRate;

        $user = User::where("uid", $uid)->first();
        if (!empty($user->is_partner)) {
            $res = [
                'success' => false,
                'message' => 'User had been partner',
            ];
            return response()->json($res, 400);
        }
        $id = $user->id;

        $checkRate = $this->referralService->checkCommissionRate($user, $commissionRate);
        if (!$checkRate['success']) {
            return response()->json($checkRate, 400);
        }

        try {
            DB::beginTransaction();
            $currentTime = Carbon::now();
            $user->is_partner = Consts::PARTNER_INACTIVE;
            $user->partner_registered_at = $currentTime;
            $user->save();
            $targetUserIds[] = $user->id;

            $downIds = AffiliateTrees::where('referrer_id', $id)->pluck('user_id')->all();
            $downTargetUsers = User::whereIn('id', $downIds)
                ->whereNull('is_partner');
            $downTargetUserIds = $downTargetUsers->pluck('id')->all();
            $downTargetUsers->update(['is_partner' => Consts::PARTNER_INACTIVE, 'partner_registered_at' => $currentTime]);

            $targetUserIds = array_merge($targetUserIds, $downTargetUserIds);
            $this->activityHistoryService->createAddPartnerActivity($request->user()->id, $targetUserIds);

            UserRates::create([
                'id' => $id,
                'commission_rate' => $commissionRate
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Success',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $e
            ], 500);
        }
    }

    public function list(Request $request)
    {
        $params = $request->all();
        $referrerId = Arr::get($params, 'invitedById', '');
        $userId = Arr::get($params, 'accountId', '');
        $email = Arr::get($params, 'email', '');
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $head = [
            'filter' => [
                'invitedById' => $referrerId,
                'accountId' => $userId,
                'email' => $email
            ],
            'page' => $page,
            'limit' => $limit
        ];

        $items = User::with('userRate', 'referrerUser', 'affiliateTreeUsers')
            ->whereNotNull('is_partner')
            ->when($referrerId, function ($query, $referrerId) {
                $query->where('referrer_id', $referrerId);
            })
            ->when($userId, function ($query, $userId) {
                $query->where('id', $userId);
            })
            ->when($email, function ($query, $email) {
                $query->where('email', $email);
            })
            ->orderByRaw('created_at IS NULL') // NULL xuống cuối
            ->orderByDesc('created_at')
            ->paginate($limit);

        // $accountIds = [699];
        //[537, 538, 539, 540, 541, 542];

        $accountIds = $items->getCollection()->pluck('id')->toArray();
        $resultBalance = $this->balanceService->listBalanceIds($accountIds, $request);
        $accountBalances = collect($resultBalance)->get("accounts", []);

        $items->setCollection($items->map(function ($item) use ($accountBalances) {
            $levelWithF0 = $item->affiliateTreeUsers->max('level');
            $accountBalance = collect($accountBalances[$item->id] ?? []);

            $spot = $accountBalance->get('spot', []);
            $feature = $accountBalance->get('feature', []);

            $spotAmountTotalUSDT = safeBigNumberInput($spot['totalAmountUSDT'] ?? 0);
            $futureAmountTotalUSDT = safeBigNumberInput($feature['totalAmountUSDT'] ?? 0);

            $totalAmountUSDT = BigNumber::new($spotAmountTotalUSDT)
                ->add(BigNumber::new($futureAmountTotalUSDT))
                ->toString();

            $spotList = $spot['list'] ?? [];
            $futureList = $feature['list'] ?? [];

            $_item = [
                'uid' => $item->uid,
                'accountId' => $item->id,
                'email' => $item->email,
                'inviteCode' => $item->referrer_code,
                'partnerStatus' => $item->is_partner,
                'rateCommission' => $item->userRate->commission_rate ?? '0',
                'affiliateProfileId' => $item->referrer_id,
                'canSetCommission' => true,
                'totalAmountUSDT' => $totalAmountUSDT,
                'spotAmountTotalUSDT' => $spotAmountTotalUSDT,
                'futureAmountTotalUSDT' => $futureAmountTotalUSDT,
                'spot' => $spotList,
                'future' => $futureList,
                'createdAt' => $item->created_at,
                'invitedByEmail' => $item->referrerUser->email ?? '',
                'isPartner' => $item->is_partner == Consts::PARTNER_ACTIVE,
                'levelWithF0' => $levelWithF0,
                'name' => $item->name,
                'phoneNumber' => $item->phone_no,
                'rootRefAccountId' => $item->affiliateTreeUsers->where('level', $levelWithF0)->first()->referrer_id ?? '',
                'updatedAt' => $item->updated_at
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

    public function update($id, Request $request)
    {
        $params = $request->all();
        $params['id'] = $id;

        $validator = Validator::make($params, [
            'id' => 'required|exists:users,id',
            'commissionRate' => 'numeric',
            'active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->messages(), 'errors' => $validator->errors()], 422);
        }

        $id = Arr::get($params, 'id');
        $commissionRate = Arr::get($params, 'commissionRate');
        $active = Arr::get($params, 'active');

        $user = User::find($id);
        if (empty($user->is_partner)) {
            $res = [
                'success' => false,
                'message' => 'Agent profile information not found',
            ];
            return response()->json($res, 404);
        }

        if (!is_null($commissionRate)) {
            $checkRate = $this->referralService->checkCommissionRate($user, $commissionRate);
            if (!$checkRate['success']) {
                return response()->json($checkRate, 400);
            }

            $oldRate = 0;
            $newRate = $commissionRate;
            $userRate = UserRates::find($id);
            if ($userRate) {
                $oldRate = BigNumber::new($userRate->commission_rate);
                $userRate->commission_rate = $commissionRate;
                $userRate->save();
            } else {
                UserRates::create([
                    'id' => $id,
                    'commission_rate' => $commissionRate
                ]);
            }

            PartnerRequest::create([
                'user_id' => $id,
                'type' => Consts::PARTNER_REQUEST_ADMIN_CHANGE_RATE,
                'detail' => Consts::PARTNER_REQUEST_DETAIL[Consts::PARTNER_REQUEST_ADMIN_CHANGE_RATE],
                'old' => $oldRate,
                'new' => $newRate,
                'status' => Consts::PARTNER_REQUEST_APPROVED,
                'processed_by' => $request->user()->id
            ]);

            $this->activityHistoryService->create([
                'page' => Consts::ACTIVITY_HISTORY_PAGE_PARTNER_ADMIN,
                'type' => Consts::ACTIVITY_HISTORY_TYPE_CHANGE_COMMISSION_RATE,
                'actor_id' => $request->user()->id,
                'target_id' => $id
            ]);
        }

        if (!is_null($active)) {
            $user->is_partner = $active ? Consts::PARTNER_ACTIVE : Consts::PARTNER_INACTIVE;
            $user->save();

            $this->activityHistoryService->create([
                'page' => Consts::ACTIVITY_HISTORY_PAGE_PARTNER_ADMIN,
                'type' => $active ? Consts::ACTIVITY_HISTORY_TYPE_CHANGE_ACTIVE : Consts::ACTIVITY_HISTORY_TYPE_CHANGE_INACTIVE,
                'actor_id' => $request->user()->id,
                'target_id' => $id
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
        ]);
    }

    public function listCommissionRequest(Request $request)
    {
        $request->validate([
            'id' => 'exitsts:partner_requests,user_id',
            'status' => 'in:' . Consts::PARTNER_REQUEST_PENDING . ',' . Consts::PARTNER_REQUEST_APPROVED . ',' . Consts::PARTNER_REQUEST_REJECT
        ]);
        $params = $request->all();
        $id = Arr::get($params, 'accountId', '');
        $status = Arr::get($params, 'status');
        $sort = Arr::get($params, 'sort', '');
        $direction = Arr::get($params, 'direction', Consts::SORT_ASC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        switch ($sort) {
            case 'email':
            case 'status':
                break;
            case 'perRate':
                $sort = 'old';
                break;
            case 'newRate':
                $sort = 'new';
                break;
            case 'createdAt':
                $sort = 'created_at';
                break;
        }

        $statusOption = [];
        foreach (Consts::PARTNER_REQUEST_STATUS as $k => $v) {
            $statusOption[] = [
                'value' => $k,
                'label' => $v
            ];
        }

        $head = [
            'filter' => [
                'accountId' => $id,
                'status' => [
                    'type' => 'select',
                    'option' => $statusOption,
                    'value' => $status ?? ''
                ]
            ],
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit
        ];

        $items = PartnerRequest::with('user')
            ->where('type', 'c')
            ->when($id, function ($query, $id) {
                $query->where('user_id', $id);
            })
            ->when(!is_null($status), function ($query, $check) use ($status) {
                $query->where('status', $status);
            })
            ->when($sort, function ($query, $sort) use ($direction) {
                if ($sort == 'email') {
                    $query->orderBy(
                        User::select('email')->whereColumn('id', 'partner_requests.user_id')
                            ->orderBy('email', $direction)
                            ->limit(1),
                        $direction
                    );
                } else $query->orderBy($sort, $direction);
            })
            ->paginate($limit);

        $items->setCollection($items->map(function ($item) {
            $_item = [
                'accountId' => $item->user_id,
                'email' => $item->user->email,
                'perRate' => $item->old,
                'newRate' => $item->new,
                'status' => $item->status,
                'createdAt' => $item->created_at,
                'createdBy' => $item->created_by,
                'id' => $item->id,
                'name' => $item->user->name,
                'requestById' => $item->created_by,
                'reason' => $item->reason,
                'updatedAt' => $item->updated_at,
                'updatedBy' => $item->processed_by
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

    public function updateCommissionRequest($id, Request $request)
    {
        $params = $request->all();
        $params['id'] = $id;

        $validator = Validator::make($params, [
            'id' => 'required|exists:partner_requests,id',
            'status' => 'in:' . Consts::PARTNER_REQUEST_PENDING . ',' . Consts::PARTNER_REQUEST_APPROVED . ',' . Consts::PARTNER_REQUEST_REJECT,
            'reason' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->messages(), 'errors' => $validator->errors()], 422);
        }

        $status = Arr::get($params, 'status', '');
        $reason = Arr::get($params, 'reason', '');

        $patrnerRequest = PartnerRequest::find($id);
        if (in_array($patrnerRequest->status, [Consts::PARTNER_REQUEST_APPROVED, Consts::PARTNER_REQUEST_REJECT])) {
            return response()->json([
                'success' => false,
                'message' => 'Status change rate commsion had been updated',
            ], 400);
        }

        try {
            DB::beginTransaction();

            if ($status == Consts::PARTNER_REQUEST_APPROVED) {
                $user = User::find($patrnerRequest->user_id);
                $commissionRate = $patrnerRequest->new;
                $checkRate = $this->referralService->checkCommissionRate($user, $commissionRate);
                if (!$checkRate['success']) {
                    DB::rollBack();
                    return response()->json($checkRate, 400);
                }
                UserRates::upsert([['id' => $patrnerRequest->user_id, 'commission_rate' => $commissionRate]], ['id'], ['commission_rate']);
            }

            $patrnerRequest->status = (string) $status;
            $patrnerRequest->reason = $reason;
            $patrnerRequest->processed_by = $request->user()->id;
            $patrnerRequest->save();

            $this->activityHistoryService->create([
                'page' => Consts::ACTIVITY_HISTORY_PAGE_PARTNER_ADMIN,
                'type' => ($status == Consts::PARTNER_REQUEST_APPROVED) ? Consts::ACTIVITY_HISTORY_TYPE_APPROVE_REQUEST : Consts::ACTIVITY_HISTORY_TYPE_REJECT_REQUEST,
                'actor_id' => $request->user()->id,
                'target_id' => $patrnerRequest->user_id
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
        ]);
    }

    public function listBalance(Request $request)
    {
        $params = $request->all();
        $accountId = Arr::get($params, 'accountId', '');
        $sort = Arr::get($params, 'sort', 'email');
        $direction = Arr::get($params, 'direction', Consts::SORT_ASC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $token = explode(' ', $request->header('Authorization'))[1];

        $head = [
            'filter' => [
                'accountId' => $accountId,
            ],
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit
        ];

        $currencies = CoinsConfirmation::query()
            ->select('coin')
            ->pluck('coin');

        $listUser = User::whereNotNull('is_partner')
            ->when($accountId, function ($query, $accountId) {
                $query->where('id', $accountId);
            })
            ->orderBy($sort, $direction)
            ->paginate($limit);
        $userIds = $listUser->pluck('id')->all();

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
                ->whereIn('b.id', $userIds)
                ->select([
                    DB::raw("'{$currency}' AS asset"),
                    'a.balance',
                    'a.available_balance',
                    'a.created_at',
                    'a.updated_at',
                    'b.id AS account_id',
                    'b.email',
                ]);

            if ($num == 1) {
                $unionQuery = $subQuery;
            } else $unionQuery->union($subQuery);
            $num++;
        }

        $balances = DB::table($unionQuery)
            ->select([
                'asset',
                'balance',
                'available_balance',
                'account_id',
                'email',
            ])
            ->get();

        $listBalance = [];
        $listSpotPriceVsUsdt = [];
        foreach ($balances as $item) {

            if (empty($listSpotPriceVsUsdt[$item->asset])) {
                if (in_array($item->asset, ['usdt', 'usd'])) {
                    $listSpotPriceVsUsdt[$item->asset] = 1;
                } else {
                    $listSpotPriceVsUsdt[$item->asset] = $this->priceService->getCurrentPrice('usdt', $item->asset)->price ?? 0;
                }
            }

            // $amount = BigNumber::new($item->balance ?? '0')->mul($listSpotPriceVsUsdt[$item->asset])->toString();
            $amount = BigNumber::new(safeBigNumberInput($item->balance))->mul(safeBigNumberInput($listSpotPriceVsUsdt[$item->asset]))->toString();
            // print_r([$item->balance, $item->asset =>$listSpotPriceVsUsdt[$item->asset]]);
            $assetDetail = [];
            if ($item->balance > 0) {
                $assetDetail = [
                    'asset' => strtoupper($item->asset),
                    'balance' => $item->balance,
                ];
            }

            if (empty($listBalance[$item->account_id])) {

                $listBalance[$item->account_id] = [
                    'accountId' => $item->account_id,
                    'email' => $item->email,
                    'amount' => $amount,
                    'detail' => [
                        'spot' => $assetDetail ? [$assetDetail] : []
                    ]
                ];
            } else {

                // $listBalance[$item->account_id]['amount'] = BigNumber::new($listBalance[$item->account_id]['amount'])->add($amount)->toString();

                $listBalance[$item->account_id]['amount'] = BigNumber::new(safeBigNumberInput($listBalance[$item->account_id]['amount']))
                    ->add(safeBigNumberInput($amount))
                    ->toString();


                if ($assetDetail) {
                    $listBalance[$item->account_id]['detail']['spot'][] = $assetDetail;
                }
            }
        }



        // dd($listSpotPriceVsUsdt);
        // Log::channel('spot_prices')->info('Spot Price Data:', $listSpotPriceVsUsdt);

        $listUser->setCollection($listUser->map(function ($item) use ($listBalance, $futureBalance) {
            $_item = $listBalance[$item->id];
            if (!empty($futureBalance[$item->id])) {
                $futureItem = $futureBalance[$item->id];

                // $_item['amount'] = BigNumber::new($_item['amount'])->add($futureItem['usdtBalance'])->toString();
                $_item['amount'] = BigNumber::new(safeBigNumberInput($_item['amount']))
                    ->add(safeBigNumberInput($futureItem['usdtBalance']))
                    ->toString();



                foreach ($futureItem['detail'] as $fAsset => $fAmount) {
                    $_item['detail']['future'][] = [
                        'asset' => $fAsset,
                        'balance' => $fAmount
                    ];
                }
            }
            return $_item;
        }));

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'head' => $head,
            'list' => $listUser->appends($params)
        ]);
    }
}
