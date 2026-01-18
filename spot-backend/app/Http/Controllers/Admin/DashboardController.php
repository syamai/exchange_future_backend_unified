<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\AccountService;
use Illuminate\Http\Request;


class DashboardController extends AppBaseController
{
    private $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    public function logsAccountsHistory(Request $request) {
        $result = $this->accountService->accountsHistory($request);
        return $this->sendResponse($result);
    }
    public function logsTransactionsHistory(Request $request) {
        $result = $this->accountService->transactionsHistory($request);
        return $this->sendResponse($result);
    }
}
