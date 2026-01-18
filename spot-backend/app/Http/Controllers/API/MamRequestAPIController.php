<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Service\Mam\MamRequestService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MamRequestAPIController extends AppBaseController
{
    private MamRequestService $mamRequestService;

    public function __construct(MamRequestService $mamRequestService)
    {
        $this->mamRequestService = $mamRequestService;
    }

    public function getMasterRequest(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $data = $this->mamRequestService->getMasterRequest($params);
        return $this->sendResponse($data);
    }

    public function getFollowerRequest(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $data = $this->mamRequestService->getFollowerRequest($params);
        return $this->sendResponse($data);
    }

    public function createJoiningRequest(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $data = $this->mamRequestService->createJoiningRequest($params);
        return $this->sendResponse($data);
    }

    public function createAssignRevokeRequest(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $data = $this->mamRequestService->createAssignRevokeRequest($params);
        return $this->sendResponse($data);
    }

    public function updateRequest(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $params['user_id'] = Auth::id();
            $data = $this->mamRequestService->updateRequest($params);
            DB::commit();
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            logger($ex);
            DB::rollBack();
            throw new HttpException(422, $ex->getMessage());
        }
    }
}
