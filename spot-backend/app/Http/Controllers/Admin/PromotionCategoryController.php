<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\PromotionCategoryService;
use App\Models\PromotionCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PromotionCategoryController extends AppBaseController
{
    private $promotionCategoryService;

    public function __construct(PromotionCategoryService $promotionCategoryService)
    {
        $this->promotionCategoryService = $promotionCategoryService;
    }

    public function index(Request $request)
    {
        try {
            $categories = $this->promotionCategoryService->getCategories($request->all());
            return $this->sendResponse($categories);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|array',
                'name.en' => 'required|string|max:255',
                'name.vi' => 'required|string|max:255',
                'key' => 'nullable|string|max:255|unique:promotion_categories,key',
                'status' => 'required|in:active,inactive'
            ]);

            $category = $this->promotionCategoryService->createCategory($validated);
            return $this->sendResponse($category, 'Category created successfully');
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function show(PromotionCategory $category)
    {
        try {
            $category = $this->promotionCategoryService->getCategory($category);
            return $this->sendResponse($category);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function update(Request $request, PromotionCategory $category)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|array',
                'name.en' => 'required|string|max:255',
                'name.vi' => 'required|string|max:255',
                'key' => 'nullable|string|max:255|unique:promotion_categories,key,' . $category->id,
                'status' => 'required|in:active,inactive'
            ]);

            $category = $this->promotionCategoryService->updateCategory($validated, $category);
            return $this->sendResponse($category, 'Category updated successfully');
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function destroy(PromotionCategory $category)
    {
        try {
            $this->promotionCategoryService->deleteCategory($category);
            return $this->sendResponse(null, 'Category deleted successfully');
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
} 