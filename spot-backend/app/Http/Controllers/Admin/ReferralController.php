<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Controllers\Controller;
use App\Http\Services\AdminReferralDashboardService;
use App\Http\Services\AdminReferralService;
use App\Http\Services\Api\ReferrerClientService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReferralController extends AppBaseController
{
    protected $referralService;
    protected $referralDashboardService;
    protected $referrerClient;

    public function __construct(
        AdminReferralService $referralService,
        AdminReferralDashboardService $referralDashboardService,
        ReferrerClientService $referrerClient
                                )
    {
        $this->referralService = $referralService;
        $this->referralDashboardService = $referralDashboardService;
        $this->referrerClient = $referrerClient;
    }

    public function getReferralsList(Request $request)
    {
        $filters = $request->only([
            'search_key',
            'from_date',
            'to_date',
            'per_page'
        ]);

        $referrals = $this->referralService->getReferralsList($filters);

        return response()->json([
            'status' => 'success',
            'data' => $referrals
        ]);
    }

    public function getReferrersList(Request $request)
    {
        $filters = $request->only([
            'search_key',
            'commission_level',
            'status',
            'sort_by',
            'sort_order',
            'per_page'
        ]);

        $referrers = $this->referralService->getReferrersList($filters);

        return response()->json([
            'status' => 'success',
            'data' => $referrers
        ]);
    }

    public function getReferrerDetails(Request $request, $id)
    {
        $details = $this->referralService->getReferrerDetails($id);

        if (empty($details)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Referrer not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $details
        ]);
    }

    public function getReferrerTransactions(Request $request, $id)
    {
        $filters = $request->only([
            'type',
            'per_page'
        ]);

        $details = $this->referralService->getReferrerTransactions($id, $filters);

        if (empty($details)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Referrer not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $details
        ]);
    }

    public function getCommissionStatistics(Request $request)
    {
        $filters = $request->only([
            'search_key',
            'from_date',
            'to_date',
            'per_page'
        ]);

        $statistics = $this->referralService->getCommissionStatistics($filters);

        return response()->json([
            'status' => 'success',
            'data' => $statistics
        ]);
    }

    /**
     * Get trade volume statistics (Dashboard)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTradeVolumeStatistics(Request $request)
    {
        $filters = $request->only([
            'period',
            'type',
            'from_date',
            'to_date'
        ]);

        $statistics = $this->referralDashboardService->getTradeVolumeStatistics($filters);

        return response()->json([
            'status' => 'success',
            'data' => $statistics
        ]);
    }

    /**
     * Get referrers summary statistics (Dashboard)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReferrersSummary(Request $request)
    {
        $filters = $request->only([
            'period'
        ]);

        $statistics = $this->referralDashboardService->getReferrersSummary($filters);

        return response()->json([
            'status' => 'success',
            'data' => $statistics
        ]);
    }

    /**
     * Get referrals summary statistics (Dashboard)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReferralsSummary(Request $request)
    {
        $statistics = $this->referralDashboardService->getReferralsSummary();

        return response()->json([
            'status' => 'success',
            'data' => $statistics
        ]);
    }

    public function getDistributedCommissionOverview() {
        $overview = $this->referralService->getDistributedCommissionOverview();

        return response()->json([
            'status' => 'success',
            'data' => $overview
        ]);
    }

    public function distributedCommissionOverviewExport(Request $request) { 
        try {
            return $this->referralService->distributedCommissionOverviewExport($request);
        } catch (Exception $error) {
            return $error->getMessage();
        }
    }

    public function getDistributedCommissionStatistics(Request $request) {
        $params = $request->all();
        $sdate = Arr::get($params, 'sdate', '');
        $edate = Arr::get($params, 'edate', '');
        $tz = Arr::get($params, 'tz', 'UTC');

        if (!$sdate || !$edate) {
            $sdate = Carbon::now()->subDays(7);
            $edate = Carbon::now();
        }

        $data = $this->referralService->getDistributedCommissionStatistics($sdate, $edate, $tz);

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);

    }

    public function referrerClientLevels(Request $request) {
        try {
            return $this->sendResponse($this->referrerClient->referrerClientLevels($request), 'referrer client levels');
        } catch (Exception $error) {
            return $error->getMessage();
        }
    }

    public function referrerClientLevel(Request $request, $level) {
        try {
            return $this->sendResponse($this->referrerClient->referrerClientLevel($level, $request), "referrer client level {$level}");
        } catch (Exception $error) {
            return $error->getMessage();
        }
    }

    public function setReferrerClientLevel(Request $request, $level)
    {
        try {
            $validated = Validator::make($request->all(), [
                'trade_min' => 'required|numeric|min:0',
                'volume'    => 'required|numeric|min:0',
                'rate'      => 'required|numeric|min:0|max:100',
                'label'     => 'required|string|max:255',
            ])->validate();

            $updated = $this->referrerClient->setReferrerLevel($level, $validated);
            return $this->sendResponse(
                $updated,
                "Set referrer client level: {$level}"
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(), // all validation messages
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

   public function overallReferralConversionRate(Request $request)
    {
        try {
            return $this->sendResponse(
                $this->referralDashboardService->overallReferralConversionRate($request),
                "Overall referral conversion rate"
            );
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function topPerformers(Request $request)
    {
        try {
            $sdate = $request->input('sdate', Carbon::now()->subDays(7)->timestamp);
            $edate = $request->input('edate', Carbon::now()->timestamp);

            $validated = Validator::make([
                'sdate' => $sdate,
                'edate' => $edate,
            ], [
                'sdate' => 'required|numeric',
                'edate' => 'required|numeric',
            ])->validate();

            return $this->sendResponse(
                $this->referralDashboardService->topPerformers($validated, $request),
                "Top Performers"
            );
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Export referrals list
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportReferralsList(Request $request)
    {
        $filters = $request->only([
            'search_key',
            'from_date',
            'to_date'
        ]);

        $data = $this->referralService->exportReferralsList($filters);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'filename' => 'referrals_list_' . date('Y-m-d_H-i-s') . '.csv'
        ]);
    }

    /**
     * Export referrers list
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportReferrersList(Request $request)
    {
        $filters = $request->only([
            'search_key',
            'commission_level',
            'status',
            'sort_by',
            'sort_order'
        ]);

        $data = $this->referralService->exportReferrersList($filters);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'filename' => 'referrers_list_' . date('Y-m-d_H-i-s') . '.csv'
        ]);
    }

    /**
     * Export commission statistics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportCommissionStatistics(Request $request)
    {
        $filters = $request->only([
            'search_key',
            'from_date',
            'to_date'
        ]);

        $data = $this->referralService->exportCommissionStatistics($filters);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'filename' => 'commission_statistics_' . date('Y-m-d_H-i-s') . '.csv'
        ]);
    }

    /**
     * Export referrer transactions
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportReferrerTransactions(Request $request, $id)
    {
        $filters = $request->only([
            'type'
        ]);

        $data = $this->referralService->exportReferrerTransactions($id, $filters);

        if (empty($data)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No data found for export'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'filename' => 'referrer_' . $id . '_transactions_' . date('Y-m-d_H-i-s') . '.csv'
        ]);
    }
}
