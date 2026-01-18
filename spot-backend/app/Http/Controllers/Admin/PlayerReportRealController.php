<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\PlayerReportRealService;
use Illuminate\Http\Request;

class PlayerReportRealController extends AppBaseController
{
    /**
     * Class constructor.
     */
    public $report;
    public function __construct(PlayerReportRealService $report)
    {
        $this->report = $report;
    }

    public function balance(Request $request) {
        return $this->sendResponse( $this->report->balance($request->all()), 'Player real balance report');
    }

    public function exportBalance(Request $request) {
        return $this->report->export($request->all());
    }
}
