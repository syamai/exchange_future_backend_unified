<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\PnlService;
use Illuminate\Http\JsonResponse;

class PnlController extends AppBaseController
{
    private PnlService $pnlService;

    public function __construct()
    {
        $this->pnlService = new PnlService();
    }

    public function getPnlUser(): JsonResponse
    {
        try {
            $pnl = $this->pnlService->getPnlUser();
            return $this->sendResponse($pnl);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }
}
