<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\PromotionService;
use Illuminate\Http\Request;
use App\Models\Promotion;
use Illuminate\Support\Facades\Log;

class PromotionController extends AppBaseController
{
    private $promotionService;

    public function __construct(PromotionService $promotionService)
    {
        $this->promotionService = $promotionService;
    }

    public function getPromotions(Request $request)
    {
        try {
            $result = $this->promotionService->getPromotions($request->all());
            return $this->sendResponse($result);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getPromotionDetails(Promotion $promotion)
    {
        try {
            $result = $this->promotionService->getPromotionDetails($promotion);
            return $this->sendResponse($result);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
}
