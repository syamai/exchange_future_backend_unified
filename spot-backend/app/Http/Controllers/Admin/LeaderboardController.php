<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;
use App\Http\Services\AdminLeaderboardService;
use App\Models\AdminLeaderboard;
use Illuminate\Support\Facades\Log;

class LeaderboardController extends AppBaseController
{
    private $leaderboardService;

    public function __construct(AdminLeaderboardService $leaderboardService)
    {
        $this->leaderboardService = $leaderboardService;
    }
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function getTopTradingVolumeRanking(Request $request)
    {
        $result = $this->leaderboardService->getTopTradingVolumeRanking($request->all());
        return $this->sendResponse($result);
    }
    public function getTopTradingVolumeRankingByUser(Request $request)
    {
        $result = $this->leaderboardService->getTopTradingVolumeRankingByUser($request->all());
        return $this->sendResponse($result);
    }
    public function changeSetting(Request $request)
    {
        try {
            $data = $this->leaderboardService->changeSetting($request->all());
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
    public function getSettingSelfTrading()
    {
        try {
            $data = $this->leaderboardService->getSettingSelfTrading();
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
    public function updateLeaderboardSetting(Request $request)
    {
        try {
            $data = $this->leaderboardService->updateLeaderboardSetting($request->all());
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getLeaderboardSetting(Request $request)
    {
        try {
            $data = $this->leaderboardService->getLeaderboardSetting($request->all());
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
    /**
     * @param Request $request
     */
}
