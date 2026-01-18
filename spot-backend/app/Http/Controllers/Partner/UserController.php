<?php

namespace App\Http\Controllers\Partner;

use App\Consts;
use App\Http\Controllers\Controller;
use App\Http\Services\BalanceService;
use App\Utils\BigNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use DB;

class UserController extends Controller
{
    private $balanceService;

    public function __construct(BalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    public function getUserQuery(Request $request) {
        $params = $request->all();
        $invitedById = Arr::get($params, 'invitedById', '');
        $phoneNumber = Arr::get($params, 'phoneNumber', '');
        $isDirectRef = Arr::get($params, 'isDirectRef');
        $accountId = Arr::get($params, 'accountId', '');
        $email = Arr::get($params, 'email', '');
        $sort = Arr::get($params, 'sort', 'createdAt');
        $direction = Arr::get($params, 'direction', Consts::SORT_ASC);
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $requestType = $request->request_type ?? '';

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
            ->join('user_security_settings AS b', 'a.user_id', 'b.id')
            ->join('spot_usdt_accounts AS c', 'a.user_id', 'c.id')
            ->join('users AS d', 'a.user_id', 'd.id')
            ->join('users AS e', 'd.referrer_id', 'e.id')
            ->leftJoin('user_rates AS f', 'f.id', 'd.id')
            ->where('a.referrer_id', $user->id)
            ->when($invitedById, function ($query, $invitedById) {
                $query->where('d.referrer_id', $invitedById);
            })
            ->when($phoneNumber, function ($query, $phoneNumber) {
                $query->where('d.phone_no', $phoneNumber);
            })
            ->when(!is_null($isDirectRef), function ($query, $check) use ($isDirectRef) {
                if($isDirectRef == 1) {$query->where('a.level', 1);}
                else {$query->where('a.level', '<>', 1);}
            })
            ->when($accountId, function ($query, $accountId) {
                $query->where('a.user_id', $accountId);
            })
            ->when($email, function ($query, $email) {
                $query->where('d.email', $email);
            })
            ->when($requestType == 'partner', function ($query, $check) {
                $query->whereNotNull('d.is_partner');
            })
            ->orderBy($sort, $direction)
            ->selectRaw("IF(a.level = 1, 1, 0) AS isDirectRef,
                b.identity_verified AS isHadKyc,
                c.available_balance AS totalUsd,
                d.id AS accountId,
                e.id AS affiliateProfileId,
                d.email AS email,
                d.name AS name,
                d.referrer_code AS inviteCode,
                e.id AS invitedById,
                e.email AS invitedByEmail,
                d.is_partner AS isPartner,
                d.status,
                IF(f.id IS NULL, 0, f.commission_rate) AS rateCommission,
                d.phone_no AS phoneNumber,
                d.id,
                d.created_at AS createdAt,
                d.updated_at AS updatedAt
                ")
            ->paginate($limit);

        $accountIds = $items->getCollection()->pluck('id')->toArray();
        $resultBalance = $this->balanceService->listBalanceIds($accountIds, $request);
        $accountBalances = collect($resultBalance)->get("accounts", []);

        $items->setCollection($items->map(function ($item) use($accountBalances) {
            $accountBalance = collect($accountBalances[$item->id] ?? []);

            $spot = $accountBalance->get('spot', []);
            $feature = $accountBalance->get('feature', []);

            $spotAmountTotalUSDT = safeBigNumberInput($spot['totalAmountUSDT'] ?? 0);
            $futureAmountTotalUSDT = safeBigNumberInput($feature['totalAmountUSDT'] ?? 0);
            
            $totalAmountUSDT = BigNumber::new($spotAmountTotalUSDT)
                ->add(BigNumber::new($futureAmountTotalUSDT))
                ->toString();
            $_item = [
                'isDirectRef' => $item->isDirectRef,
                'isHadKyc' => $item->isHadKyc,
                'totalUsd' => $totalAmountUSDT, //$item->totalUsd,
                'accountId' => $item->accountId,
                'affiliateProfileId' => $item->affiliateProfileId,
                'email' => $item->email,
                'name' => $item->name,
                'inviteCode' => $item->inviteCode,
                'invitedById' => $item->invitedById,
                'invitedByEmail' => $item->invitedByEmail,
                'isPartner' => $item->isPartner,
                'status' => $item->status,
                'rateCommission' => $item->rateCommission,
                'phoneNumber' => $item->phoneNumber,
                'id' => $item->id,
                'createdAt' => $item->createdAt,
                'createdAt' => $item->updatedAt
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

    public function getPartnerList(Request $request) {
        $request->request_type = 'partner';
        return $this->getUserQuery($request);
    }
}
