<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\UsersStatisticsOverviewService;
use Illuminate\Http\Request;

class UsersStatisticsOverviewController extends AppBaseController
{
    private $overViews;

    public function __construct(UsersStatisticsOverviewService $overViews) {
        $this->overViews = $overViews;
    }

    public function noDeposit(Request $request) { 
        return $this->sendResponse($this->overViews->usersNoDeposit($request->all()), 'Users statistics overview deposit no');
    }

    public function topDeposit(Request $request, $currency) {
         return $this->sendResponse($this->overViews->topDeposit($request->all(), $currency), 'Users statistics overview top deposit');
    }
    public function pendingWithdraw(Request $request) {
       return $this->sendResponse($this->overViews->pendingWithdrawAll($request->all()), 'Users statistics overview pending withdraw');
    }
    public function topWithdraw(Request $request, $currency) {
        return $this->sendResponse($this->overViews->topWithdraw($request->all(), $currency), 'Users statistics overview top withdraw');
    }
    public function listGains(Request $request) {
        return $this->sendResponse($this->overViews->topGains($request->all()), 'Users statistics overview top gains');
    }
    public function listLosers(Request $request) {
        return $this->sendResponse($this->overViews->topLosers($request->all()), 'Users statistics overview top losers');
    }
}
