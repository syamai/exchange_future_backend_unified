<?php

namespace App\Http\Controllers\Admin;

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
            $result = $this->promotionService->adminGetPromotions($request->all());
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

    public function createPromotion(Request $request)
    {
        try {
            $result = $this->promotionService->createPromotion($request->all());
            return $this->sendResponse($result);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function updatePromotion(Request $request, Promotion $promotion)
    {
        try {
            $result = $this->promotionService->updatePromotion($request->all(), $promotion);
            return $this->sendResponse($result);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function pinPromotion(Request $request, Promotion $promotion)
    {
        try {
            $result = $this->promotionService->pinPromotion($request->all(), $promotion);
            return $this->sendResponse($result);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function deletePromotion(Promotion $promotion)
    {
        try {
            $result = $this->promotionService->deletePromotion($promotion);
            return $this->sendResponse($result);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
}
