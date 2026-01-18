<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Service\Mam\MamFollowerService;

class MamFollowerAPIController extends AppBaseController
{
    private MamFollowerService $mamFollowerService;

    public function __construct(MamFollowerService $mamFollowerService)
    {
        $this->mamFollowerService = $mamFollowerService;
    }

    public function getInvestments(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $params['get_investment'] = true;
        $data = $this->mamFollowerService->getInvestments($params);
        return $this->sendResponse($data);
    }

    public function getDetailFollower(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $data = $this->mamFollowerService->getDetailFollower($params);
        return $this->sendResponse($data);
    }

    public function getFollowerOverview(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $data = $this->mamFollowerService->getFollowerOverview($params);
        return $this->sendResponse($data);
    }
}
