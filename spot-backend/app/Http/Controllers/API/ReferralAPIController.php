<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Http\Services\ReferralService;
use Illuminate\Support\Facades\Auth;

class ReferralAPIController extends AppBaseController
{
    private ReferralService $referralService;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }

    public function changeStatus(Request $request): JsonResponse
    {
        $params = $request->all();
        $validator = Validator::make($params, [
            'status' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }
        $status = $request->status;
        DB::beginTransaction();

        try {
            $changeStatus = $this->referralService->changeStatus($status);
            DB::commit();
            return $this->sendResponse($changeStatus);
        } catch (HttpException $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getTotalReferrer()
    {
        $userId = auth('api')->id();
        return $this->referralService->getTotalReferrer($userId);
    }

    public function getReferralSettings(): JsonResponse
    {
        try {
            $getReferralSettings = $this->referralService->getReferralSettings();
            return $this->sendResponse($getReferralSettings);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function updateReferralSettings(Request $request): JsonResponse
    {
        $params = $request->all();

        $validator = Validator::make($params, [
            'number_of_levels' => 'required|numeric|min:1|max:5',
            'refund_rate' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        DB::beginTransaction();

        try {
            $updateReferralSettings = $this->referralService->updateReferralSettings($params);
            Db::commit();
            return $this->sendResponse($updateReferralSettings);
        } catch (HttpException $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getReferralHistory(Request $request): JsonResponse
    {
        try {
            $getReferralHistory = $this->referralService->getReferralHistory($request->all());
            return $this->sendResponse($getReferralHistory);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Withdraw commission to spot account
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawCommission(Request $request)
    {
        $this->validate($request, [
            'amount' => 'required|numeric|min:0.00000001'
        ]);

        $userId = Auth::id();
        $result = $this->referralService->withdrawCommission($userId, $request->amount);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

    /**
     * Get commission withdrawal history
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWithdrawalHistory(Request $request)
    {
        $this->validate($request, [
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable|after_or_equal:start_date',
            'limit' => 'integer|min:1|max:100|nullable'
        ]);

        $userId = Auth::id();
        $history = $this->referralService->getCommissionWithdrawalHistory($userId, $request->all());

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * Get commission overview
     */
    public function getCommissionOverview(Request $request)
    {
        $this->validate($request, [
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable|after_or_equal:start_date'
        ]);

        $userId = $request->user()->id;
        $params = $request->only(['start_date', 'end_date']);
        $result = $this->referralService->getCommissionOverview($userId, $params);
        return response()->json($result);
    }

    /**
     * Get commission daily trends
     */
    public function getCommissionDailyTrends(Request $request)
    {
        $userId = $request->user()->id;
        $params = $request->all();
        $result = $this->referralService->getCommissionDailyTrends($userId, $params);
        return response()->json($result);
    }

    /**
     * Get commission monthly trends
     */
    public function getCommissionMonthlyTrends(Request $request)
    {
        $userId = $request->user()->id;
        $params = $request->all();
        $result = $this->referralService->getCommissionMonthlyTrends($userId, $params);
        return response()->json($result);
    }

    /**
     * Get commission history
     */
    public function getCommissionHistory(Request $request)
    {
        $userId = $request->user()->id;
        $params = $request->all();
        $result = $this->referralService->getCommissionHistory($userId, $params);
        return response()->json($result);
    }

    /**
     * Export commission history to CSV
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportCommissionHistory(Request $request)
    {
        $userId = auth()->id();
        $params = $request->all();

        $data = $this->referralService->exportCommissionHistory($userId, $params);

        $filename = 'commission_history_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get referral overview including total referrals, active referrals and conversion rate
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReferralOverview(Request $request)
    {
        $this->validate($request, [
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable|after_or_equal:start_date'
        ]);

        try {
            $userId = auth()->id();
            $params = $request->only(['start_date', 'end_date']);
            $result = $this->referralService->getReferralOverview($userId, $params);
            
            return $this->sendResponse($result);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }

    /**
     * Get referral list with detailed information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReferralList(Request $request)
    {
        try {
            $userId = auth()->id();
            $params = $request->all();
            
            $result = $this->referralService->getReferralList($userId, $params);
            
            return $this->sendResponse($result);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }

    /**
     * Export referral list to CSV
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportReferralList(Request $request)
    {
        try {
            $userId = auth()->id();
            $params = $request->all();

            $data = $this->referralService->exportReferralList($userId, $params);

            $filename = 'referral_list_' . date('Y-m-d_His') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($data) {
                $file = fopen('php://output', 'w');
                foreach ($data as $row) {
                    fputcsv($file, $row);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }
}
