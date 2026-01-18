<?php

namespace App\Http\Controllers;

use App\Http\Services\ToolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ToolController extends AppBaseController
{
    private ToolService $toolService;

    public function __construct(ToolService $toolService)
    {
        $this->toolService = $toolService;
    }

    /**
     * Show the application dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateData(Request $request): JsonResponse
    {
        try {
            $this->toolService-> updateAccounts($request);
            return $this->sendResponse(true);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    /**
     * Clear Cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            Cache::flush();
            Redis::flushall();
            return $this->sendResponse(true);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }
}
