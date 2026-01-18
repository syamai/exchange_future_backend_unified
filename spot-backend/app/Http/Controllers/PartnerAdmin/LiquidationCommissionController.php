<?php

namespace App\Http\Controllers\PartnerAdmin;

use App\Consts;
use App\Http\Controllers\Controller;
use App\Http\Services\LiquidationCommissionService;
use App\Jobs\SubmitLiquidationCommission;
use App\Models\LiquidationCommission;
use App\Models\LiquidationCommissionDetail;
use App\Models\User;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LiquidationCommissionController extends Controller
{

    private $liquidationCommissionService;

    public function __construct(LiquidationCommissionService $liquidationCommissionService)
    {
        $this->liquidationCommissionService = $liquidationCommissionService;
    }

    public function updateRateUser($id, Request $request)
    {
        $params = $request->all();
        $params['id'] = $id;

        $validator = Validator::make($params, [
            'id' => 'required|exists:users,id',
            'rate' => 'required|numeric|min:0|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->messages(), 'errors' => $validator->errors()], 422);
        }

        $id = Arr::get($params, 'id');
        $rate = Arr::get($params, 'rate');

        $user = User::find($id);
        if (empty($user->is_partner)) {
            $res = [
                'success' => false,
                'message' => 'Agent profile information not found',
            ];
            return response()->json($res, 404);
        }

        $this->liquidationCommissionService->updateUserRate($rate, $id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Success',
        ]);
    }

    public function getUnprocessed(Request $request)
    {
        $params = $request->all();

        $limit = intval($request->limit ?? Consts::DEFAULT_PER_PAGE);
        if ($limit < 1) {
            $limit = Consts::DEFAULT_PER_PAGE;
        }
        $searchKey = trim($request->search_key ?? "");
        $date = $request->date ?? "";


        $items = LiquidationCommission::userWithWhereHas($searchKey)
            ->where(['status' => 'init'])
            ->when($date, function ($query) use ($date) {
                $date = Carbon::parse($date)->toDateString();
                return $query->where('date', $date);
            })
            ->orderByDesc('date')
            ->paginate($limit);
        return response()->json([
            'success' => true,
            'message' => 'Success',
            'list' => $items->appends($params)
        ]);
    }

    public function getHistory(Request $request)
    {
        $params = $request->all();

        $limit = intval($request->limit ?? Consts::DEFAULT_PER_PAGE);
        if ($limit < 1) {
            $limit = Consts::DEFAULT_PER_PAGE;
        }
        $searchKey = trim($request->search_key ?? "");
        $date = $request->date ?? "";


        $items = LiquidationCommission::userWithWhereHas($searchKey)
            ->where(['status' => 'completed'])
            ->when($date, function ($query) use ($date) {
                $date = Carbon::parse($date)->toDateString();
                return $query->where('date', $date);
            })
            ->orderByDesc('date')
            ->paginate($limit);
        return response()->json([
            'success' => true,
            'message' => 'Success',
            'list' => $items->appends($params)
        ]);
    }

    public function updateRateUnprocessed($id, Request $request)
    {
        $params = $request->all();
        $params['id'] = $id;

        $validator = Validator::make($params, [
            'id' => 'required|exists:liquidation_commissions,id',
            'rate' => 'required|numeric|min:0|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->messages(), 'errors' => $validator->errors()], 422);
        }

        $rate = $request->rate ?? 0;
        $liquidationCommission = LiquidationCommission::find($id);

        if ($liquidationCommission->isSubmit) {
            $res = [
                'success' => false,
                'message' => 'No permission to edit Liq Commission Rate',
            ];
            return response()->json($res, 404);
        }
        DB::beginTransaction();
        try {

            $liquidationCommission->update(['rate' => $rate]);
            $this->liquidationCommissionService->updateUserRate($rate, $liquidationCommission->user_id, $request->user());

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Success',
                'data' => $liquidationCommission->refresh()
            ]);
        } catch (Exception $ex) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during execution.',
            ], 400);
        }

    }

    public function getDetail($id, Request $request)
    {
        $params = $request->all();

        $limit = intval($request->limit ?? Consts::DEFAULT_PER_PAGE);
        if ($limit < 1) {
            $limit = Consts::DEFAULT_PER_PAGE;
        }
        $searchKey = trim($request->search_key ?? "");

        //get obj
        $liquidationCommission = LiquidationCommission::userWithWhereHas()->find($id);
        if (!$liquidationCommission) {
            $res = [
                'success' => false,
                'message' => 'Not found',
            ];
            return response()->json($res, 404);
        }

        $items = LiquidationCommissionDetail::userWithWhereHas($searchKey)
            ->where('liquidation_commission_id', $liquidationCommission->id)
            ->orderByDesc('id')
            ->paginate($limit);
        $items->setCollection($items->map(function ($item) use ($liquidationCommission) {
            $item['rate'] = $liquidationCommission->rate;
            $item['amount_receive'] = BigNumber::round(BigNumber::new($item->amount)->mul($liquidationCommission->rate)->div(100), BigNumber::ROUND_MODE_HALF_UP, 2);
            $item['complete_at'] = $liquidationCommission->complete_at;
            return $item;
        }));

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'info' => $liquidationCommission,
            'list' => $items->appends($params)
        ]);
    }

    public function exportDetail($id, Request $request) {

        //get obj
        $liquidationCommission = LiquidationCommission::userWithWhereHas()->find($id);
        if (!$liquidationCommission) {
            $res = [
                'success' => false,
                'message' => 'Not found',
            ];
            return response()->json($res, 404);
        }

        $data = $this->liquidationCommissionService->exportDetail($liquidationCommission, $request);
        if ($data) {
            return response()->json([
                'success' => true,
                'message' => 'Success',
                'data' => $data
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => 'export: IsEmpty',
        ], 404);

    }

    public function sendUnprocessed(Request $request)
    {
        try {
            $params = $request->all();
            $validator = Validator::make($params, [
                'ids' => 'required|array',
                'ids.*' => 'exists:liquidation_commissions,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => $validator->messages(), 'errors' => $validator->errors()], 422);
            }

            $ids = $request->ids ?? [];

            //check is submit
            $dateCheck = Carbon::now()->subHours(2)->toDateString();
            $check = LiquidationCommission::whereIn('id', $ids)
                ->where(function ($query) use ($dateCheck) {
                    $query->orWhere('date', '>=', $dateCheck)
                        ->orWhere('status', '!=', 'init');
                })
                ->get();
            if ($check->isNotEmpty()) {
                $res = [
                    'success' => false,
                    'message' => 'No permission to send Liq Commission',
                ];
                return response()->json($res, 403);
            }

            // update status and set job update balance
            LiquidationCommission::whereIn('id', $ids)
                ->update(['status' => 'pending']);

            foreach ($ids as $id) {
                SubmitLiquidationCommission::dispatch($id)->onQueue(Consts::QUEUE_BALANCE_LIQ_COMMISSION);
            }

            return response()->json([
                'success' => true,
                'message' => 'Success'
            ]);

        } catch (Exception $ex) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during execution.',
            ], 400);
        }

    }


}
