<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Service\Mam\MamAccountService;
use Illuminate\Support\Facades\Artisan;

class MamBalanceAPIController extends AppBaseController
{
    private MamAccountService $mamAccountService;

    public function __construct(MamAccountService $mamAccountService)
    {
        $this->mamAccountService = $mamAccountService;
    }

    public function getBalance(Request $request): JsonResponse
    {
        $params = $request->all();
        $data = $this->mamAccountService->getBalance($params);
        return $this->sendResponse($data);
    }

    public function getUserTransferHistory(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $data = $this->mamAccountService->getUserTransferHistory($params);
        return $this->sendResponse($data);
    }

    public function runProcessDaily(Request $request): JsonResponse
    {
        $params = $request->all();
        Artisan::call('mam:1_recalculate_master_balance');
        Artisan::call('mam:2_recalculate_follower_balance');
        return $this->sendResponse(true);
    }

    public function runProcessMonthly(Request $request): JsonResponse
    {
        Artisan::call('mam:1_recalculate_master_balance');
        Artisan::call('mam:2_recalculate_follower_balance');
        Artisan::call('mam:3_calculate_commission');
        Artisan::call('mam:3_pay_commission');
        Artisan::call('mam:4_handle_join_assign_request');
        Artisan::call('mam:5_fix_revoke_amount');
        Artisan::call('mam:6_handle_revoke_round_1');
        Artisan::call('mam:7_handle_revoke_round_2');
        Artisan::call('mam:8_handle_revoke_round_3');
        Artisan::call('mam:9_cancel_pending_request');
        Artisan::call('mam:10_recalculate_capital');
        Artisan::call('mam:11_calculate_new_follower_balance');
        return $this->sendResponse(true);
    }
}
