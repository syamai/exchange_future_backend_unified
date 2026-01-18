<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Service\Mam\MamCommissionService;

class MamCommissionAPIController extends AppBaseController
{
    private MamCommissionService $mamCommissionService;

    public function __construct(MamCommissionService $mamCommissionService)
    {
        $this->mamCommissionService = $mamCommissionService;
    }

    public function getCommission(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $data = $this->mamCommissionService->getCommission($params);
        return $this->sendResponse($data);
    }
}
