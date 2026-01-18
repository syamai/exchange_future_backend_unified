<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\Api\ReferrerClientService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReferrerClientController extends AppBaseController
{
    private $referrer;

    public function __construct(ReferrerClientService $referrer) {
        $this->referrer = $referrer;
    }

    public function tierProgress(Request $request) { 
        try {
            return $this->sendResponse($this->referrer->tierProgress($request), 'Tier progress referrer client');
        } catch (Exception $error) {
            return $error->getMessage();
        }
    }

    public function recentActivities(Request $request) { 
        try {
            return $this->sendResponse($this->referrer->recentActivities($request), 'Reccent activities referrer client');
        } catch (Exception $error) {
            return $error->getMessage();
        }
    }

    public function recentActivitiesExport(Request $request) { 
        try {
            return $this->referrer->recentActivitiesExport($request);
        } catch (Exception $error) {
            return $error->getMessage();
        }
    }

    public function leaderboard(Request $request) {   
        try {
            return $this->sendResponse($this->referrer->leaderboard($request), 'Leaderboard referrer client');
        } catch (Exception $error) { 
            return $error->getMessage();
        }
        
    }

    public function dailyCommission(Request $request) {
        try {
            $sdate = $request->input('sdate', Carbon::now()->subDays(6)->toDateString());
            $edate = $request->input('edate', Carbon::now()->toDateString());

            $validated = Validator::make([
                'sdate' => $sdate,
                'edate' => $edate,
            ], [
                'sdate' => 'required|string',
                'edate' => 'required|string',
            ])->validate();
            return $this->sendResponse($this->referrer->dailyCommission($validated, $request), 'The total referral earnings from the past 7 days.');
        } catch (Exception $error) {
            return $error->getMessage();
        }
    }

    public function rankingListWeekSub(Request $request) {
        try {
            return $this->sendResponse($this->referrer->rankingListWeekSub($request), 'Referrer Commission Ranking');
        } catch (Exception $error) {
            return $error->getMessage();
        }
    }

}
