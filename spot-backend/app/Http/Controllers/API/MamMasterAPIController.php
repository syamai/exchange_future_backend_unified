<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Service\Mam\MamMasterService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MamMasterAPIController extends AppBaseController
{
    private MamMasterService $mamMasterService;

    public function __construct(MamMasterService $mamMasterService)
    {
        $this->mamMasterService = $mamMasterService;
    }

    public function getMaster(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $data = $this->mamMasterService->getMaster($params);
        return $this->sendResponse($data);
    }

    public function getFollowers(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $data = $this->mamMasterService->getFollowers($params);
        return $this->sendResponse($data);
    }

    public function createMaster(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $params['user_id'] = Auth::id();
            $data = $this->mamMasterService->createMaster($params);
            DB::commit();
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            logger($ex);
            DB::rollBack();
        }
        throw new HttpException(422, $ex->getMessage());
    }

    public function closeMaster(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $params['user_id'] = Auth::id();
            $data = $this->mamMasterService->closeMaster($params);
            DB::commit();
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            logger($ex);
            DB::rollBack();
            return $this->sendResponse($ex->getMessage());
        }
    }

    public function updateNextPerformanceFee(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $data = $this->mamMasterService->updateNextPerformanceFee($params);
        return $this->sendResponse($data);
    }

    public function getMasterOverview(Request $request): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::id();
        $data = $this->mamMasterService->getMasterOverview($params);
        return $this->sendResponse($data);
    }
}
