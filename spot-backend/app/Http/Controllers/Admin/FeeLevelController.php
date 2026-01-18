<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\FeeLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use App\Consts;
use App\Http\Services\MasterdataService;
use Illuminate\Http\JsonResponse;

class FeeLevelController extends AppBaseController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $input = $request->all();
            $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
            $data = FeeLevel::filter($input)->paginate($limit);
            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $input = $request->all();
            $feeLevel = FeeLevel::find($id);
            if (empty($feeLevel)) {
                return $this->sendError('id not found', 401);
            }
            $data = FeeLevel::where('id', $id)->update($input);
            MasterdataService::clearCacheOneTable('fee_levels');
            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }
}
