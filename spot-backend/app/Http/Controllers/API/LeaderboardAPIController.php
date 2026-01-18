<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\LeaderboardService;

class LeaderboardAPIController extends AppBaseController
{
    private $leaderboardService;

    public function __construct(LeaderboardService $LeaderboardService)
    {
        $this->leaderboardService = $LeaderboardService;
    }
    //
    public function getTopTradingVolume(Request $request)
    {
        $result = $this->leaderboardService->getTopTradingVolume($request->all());
        return $this->sendResponse($result);
    }
}
