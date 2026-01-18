<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\FaqSubCategory;
use App\Models\Inquiry;
use App\Models\InquiryType;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FaqController extends AppBaseController
{
    public function getFaqs(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $data = Faq::with(['category', 'subCategory'])
            ->filter($input)
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        return $this->sendResponse($data);
    }

    public function storeFaqs(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cat_id' => 'required|integer|exists:faq_categories,id',
                'sub_cat_id' => 'required|integer|exists:faq_sub_categories,id',
                'title_en' => 'required|string|max:200',
                'content_en' => 'required|string',
                'title_vi' => 'required|string|max:200',
                'content_vi' => 'required|string',
                'title_ko' => 'required|string|max:200',
                'content_ko' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->errors());
            }

            $faqData = $request->only(['cat_id', 'sub_cat_id', 'title_en', 'content_en', 'title_vi', 'content_vi', 'title_ko', 'content_ko']);
            //check sub cat, cat
            $subCategory = FaqSubCategory::find($request->sub_cat_id);
            if (!$subCategory || $subCategory->cat_id != $request->cat_id) {
                return $this->sendError(__('exception.not_found'));
            }

            $faqData['status'] = 'enable';

            $faq = Faq::create($faqData);
            return $this->sendResponse($faq->load(['category', 'subCategory']));
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function updateFaq($id, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cat_id' => 'required|integer|exists:faq_categories,id',
                'sub_cat_id' => 'required|integer|exists:faq_sub_categories,id',
                'title_en' => 'required|string|max:200',
                'content_en' => 'required|string',
                'title_vi' => 'required|string|max:200',
                'content_vi' => 'required|string',
                'title_ko' => 'required|string|max:200',
                'content_ko' => 'required|string',
                'status' => 'required|in:disable,enable'
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->errors());
            }

            $faq = Faq::find($id);
            if (!$faq) {
                return $this->sendError(__('exception.not_found'));
            }

            $faqData = $request->only(['cat_id', 'sub_cat_id', 'title_en', 'content_en', 'title_vi', 'content_vi', 'title_ko', 'content_ko', 'status']);
            //check sub cat, cat
            $subCategory = FaqSubCategory::find($request->sub_cat_id);
            if (!$subCategory || $subCategory->cat_id != $request->cat_id) {
                return $this->sendError('Category not found');
            }

            $faq->update($faqData);
            $faq->refresh();
            return $this->sendResponse($faq->load(['category', 'subCategory']));
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getFaq($id)
    {
        try {
            $faq = Faq::find($id);
            if (!$faq) {
                return $this->sendError(__('exception.not_found'));
            }
            return $this->sendResponse($faq->load(['category', 'subCategory']));
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function destroyFaq($id)
    {
        try {

            $faq = Faq::find($id);
            if (!$faq) {
                return $this->sendError(__('exception.not_found'));
            }
            $faq->delete();
            return $this->sendResponse(true,"success");
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function getCategories(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', 0);
        $data = FaqCategory::with(['subCategories'])
            ->filter($input)
            ->orderBy('created_at', 'desc')
            ->when($limit > 0, function ($query) use ($limit){
                return $query->paginate($limit);
            }, function ($query){
                return $query->where('status', 'enable')->get();
            });


        return $this->sendResponse($data);
    }

    public function storeCategories(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title_en' => 'required|string|max:200',
                'title_vi' => 'required|string|max:200',
                'title_ko' => 'required|string|max:200',
                'sub_cats.*.title_en' => 'required|string|max:200',
                'sub_cats.*.title_vi' => 'required|string|max:200',
                'sub_cats.*.title_ko' => 'required|string|max:200',
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->errors());
            }

            $catData = $request->only(['title_en', 'title_vi', 'title_ko']);
            $catData['status'] = 'enable';
            $category = FaqCategory::create($catData);
            if ($request->has('sub_cats')) {
                foreach ($request->sub_cats as $subCatData) {
                    $category->subCategories()->create($subCatData);
                }
            }

            return $this->sendResponse($category->load('subCategories'));
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getCategory($id)
    {
        try {
            $category = FaqCategory::find($id);
            if (!$category) {
                return $this->sendError(__('exception.not_found'));
            }
            return $this->sendResponse($category->load('subCategories'));
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function updateCategory($id, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title_en' => 'required|string|max:200',
                'title_vi' => 'required|string|max:200',
                'title_ko' => 'required|string|max:200',
                'sub_cats.*.id' => 'nullable|integer|exists:faq_sub_categories,id',
                'sub_cats.*.title_en' => 'required|string|max:200',
                'sub_cats.*.title_vi' => 'required|string|max:200',
                'sub_cats.*.title_ko' => 'required|string|max:200',
                'status' => 'required|in:disable,enable'
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->errors());
            }

            $category = FaqCategory::find($id);
            if (!$category) {
                return $this->sendError(__('exception.not_found'));
            }

            $catData = $request->only(['title_en', 'title_vi', 'title_ko', 'status']);
            $category->update($catData);

            if ($request->has('sub_cats')) {
                foreach ($request->sub_cats as $subCatData) {
                    if (isset($subCatData['cat_id'])) {
                        unset($subCatData['cat_id']);
                    }
                    if (isset($subCatData['id'])) {
                        $subId = $subCatData['id'];
                        //unset($subCatData['id']);
                        FaqSubCategory::where(['id'=> $subId, 'cat_id' => $category->id])->update($subCatData);
                    } else {
                        $category->subCategories()->create($subCatData);
                    }
                }
            }

            $category->refresh();
            return $this->sendResponse($category->load('subCategories'));
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function destroyCategory($id)
    {
        try {

            $category = FaqCategory::find($id);
            if (!$category) {
                return $this->sendError(__('exception.not_found'));
            }

            $subCats = $category->subCategories()->pluck('id');
            if ($category->faqs()->exists() || ($subCats && Faq::wherein('sub_cat_id', $subCats)->exists())) {
                //return $this->sendError('This category cannot be deleted because it is used.');
                return $this->sendError(__('exception.cat_used'));
            }

            $category->subCategories()->delete();
            $category->delete();
            return $this->sendResponse(true,"success");
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function destroySubCategory($id)
    {
        try {

            $subCategory = FaqSubCategory::find($id);
            if (!$subCategory) {
                return $this->sendError(__('exception.not_found'));
            }

            if ($subCategory->faqs()->exists()) {
                //return $this->sendError('This sub category cannot be deleted because it is used.');
                return $this->sendError(__('exception.sub_cat_used'));
            }

            $subCategory->delete();
            return $this->sendResponse(true,"success");
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }
}
