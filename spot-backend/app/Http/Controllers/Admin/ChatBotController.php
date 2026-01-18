<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Models\Chatbot;
use App\Models\ChatbotCategory;
use App\Models\ChatbotSubCategory;
use App\Models\ChatbotType;
use App\Models\Faq;
use App\Models\FaqSubCategory;
use App\Utils;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatBotController extends AppBaseController
{

    public function getTypes(Request $request)
    {
        $input = $request->all();
        $data = ChatbotType::filter($input)
            ->orderBy('updated_at', 'desc')
            ->select(['id', 'name'])
            ->get();

        return $this->sendResponse($data);
    }

    public function getCategories(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', 0);
        $data = ChatbotCategory::with(['type', 'subCategories', 'admin'])
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
        $admin = $request->user();
        $validator = Validator::make($request->all(), [
            'type_id' => 'required|integer|exists:chatbot_types,id',
            'title_en' => 'required|string|max:200',
            'title_vi' => 'required|string|max:200',
            'status' => 'required|in:enable,disable',
            'sub_cats.*.title_en' => 'required|string|max:200',
            'sub_cats.*.title_vi' => 'required|string|max:200',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        DB::beginTransaction();
        try {

            $catData = $request->only(['type_id', 'title_en', 'title_vi', 'status']);
            $catData['admin_id'] = $admin->id;
            $category = ChatbotCategory::create($catData);
            if ($request->has('sub_cats')) {
                foreach ($request->sub_cats as $subCatData) {
                    $category->subCategories()->create($subCatData);
                }
            }

            //log activity
            $category->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_CHATBOT,
                'action' => 'Create new Category'
            ]);

            DB::commit();
            return $this->sendResponse($category->load('type', 'subCategories', 'admin'));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function updateCategory($id, Request $request)
    {
        $admin = $request->user();

        $validator = Validator::make($request->all(), [
            'title_en' => 'required|string|max:200',
            'title_vi' => 'required|string|max:200',
            'sub_cats.*.id' => 'nullable|integer|exists:chatbot_sub_categories,id',
            'sub_cats.*.title_en' => 'required|string|max:200',
            'sub_cats.*.title_vi' => 'required|string|max:200',
            'status' => 'required|in:disable,enable'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $category = ChatbotCategory::find($id);
        if (!$category) {
            return $this->sendError(__('exception.not_found'));
        }

        DB::beginTransaction();
        try {
            $catData = $request->only(['title_en', 'title_vi', 'status']);
            $catData['admin_id'] = $admin->id;
            $category->update($catData);

            if ($request->has('sub_cats')) {
                foreach ($request->sub_cats as $subCatData) {
                    if (isset($subCatData['cat_id'])) {
                        unset($subCatData['cat_id']);
                    }
                    if (isset($subCatData['id'])) {
                        $subId = $subCatData['id'];
                        ChatbotSubCategory::where(['id'=> $subId, 'cat_id' => $category->id])->update($subCatData);
                    } else {
                        $category->subCategories()->create($subCatData);
                    }
                }
            }

            //log activity
            $category->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_CHATBOT,
                'action' => 'Update Category'
            ]);

            $category->refresh();
            DB::commit();

            return $this->sendResponse($category->load('type', 'subCategories', 'admin'));
        } catch (Exception $ex) {
            DB::rollBack();
            return $this->sendError($ex->getMessage());
        }
    }

    public function updateStatusCategory($id, Request $request)
    {
        $admin = $request->user();
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:enable,disable'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $category = ChatbotCategory::find($id);
        if (!$category) {
            return $this->sendError(__('exception.not_found'));
        }

        $status = $request->status ?? '';
        if ($status == $category->status) {
            return $this->sendResponse(true, "Status updated successfully.");
        }

        DB::beginTransaction();
        try {
            $data = [
                'admin_id' => $admin->id,
                'status' => $status,
            ];

            $category->update($data);

            //log activity
            $category->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_CHATBOT,
                'action' => 'Update Status: ' . ucfirst($status)
            ]);
            DB::commit();

            return $this->sendResponse(true, "Status changed successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getCategory($id)
    {
        try {
            $category = ChatbotCategory::find($id);
            if (!$category) {
                return $this->sendError(__('exception.not_found'));
            }
            return $this->sendResponse($category->load('type', 'subCategories', 'admin'));
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function destroyCategory($id, Request $request)
    {
        $admin = $request->user();
        $category = ChatbotCategory::find($id);
        if (!$category) {
            return $this->sendError(__('exception.not_found'));
        }

        $subCats = $category->subCategories()->pluck('id');
        if ($category->chatbots()->exists() || ($subCats && Chatbot::wherein('sub_cat_id', $subCats)->exists())) {
            return $this->sendError(__('exception.cat_used'));
        }

        DB::beginTransaction();
        try {

            //$category->subCategories()->delete();
            $category->delete();

            //log activity
            $category->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_CHATBOT,
                'action' => 'Delete Category'
            ]);

            DB::commit();

            return $this->sendResponse(true,"success");
        } catch (Exception $ex) {
            DB::rollBack();
            return $this->sendError($ex->getMessage());
        }
    }

    public function destroySubCategory($id)
    {

        try {
            $subCategory = ChatbotSubCategory::find($id);
            if (!$subCategory) {
                return $this->sendError(__('exception.not_found'));
            }

            if ($subCategory->chatbots()->exists()) {
                //return $this->sendError('This sub category cannot be deleted because it is used.');
                return $this->sendError(__('exception.sub_cat_used'));
            }

            $subCategory->delete();
            return $this->sendResponse(true,"success");
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function getChatBots(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $data = Chatbot::with(['category', 'subCategory', 'admin'])
            ->filter($input)
            ->orderBy('updated_at', 'desc')
            ->paginate($limit);

        return $this->sendResponse($data);
    }

    public function storeChatBot(Request $request)
    {
        $admin = $request->user();
        $validator = Validator::make($request->all(), [
            'type_id' => 'required|integer|exists:chatbot_types,id',
            'cat_id' => 'required|integer|exists:chatbot_categories,id',
            'sub_cat_id' => 'required|integer|exists:chatbot_sub_categories,id',
            'link_page' => 'nullable|url',
            'question_en' => 'required|string|max:200',
            'answer_en' => 'required|string',
            'question_vi' => 'required|string|max:200',
            'answer_vi' => 'required|string',
            'status' => 'required|in:enable,disable',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        //check type
        $type = ChatbotType::find($request->type_id);
        if (!$type) {
            return $this->sendError(__('exception.not_found'));
        }

        //check sub cat, cat
        $subCategory = ChatbotSubCategory::find($request->sub_cat_id);
        if (!$subCategory || $subCategory->cat_id != $request->cat_id) {
            return $this->sendError(__('exception.not_found'));
        }

        DB::beginTransaction();
        try {

            $data = $request->only(['type_id', 'cat_id', 'sub_cat_id', 'link_page', 'question_en', 'answer_en', 'question_vi', 'answer_vi', 'status']);

            $data['admin_id'] = $admin->id;

            $object = Chatbot::create($data);

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_CHATBOT,
                'action' => 'Create new ' . $type->name
            ]);

            DB::commit();

            return $this->sendResponse($object->load(['type', 'admin','category', 'subCategory']));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getChatBot($id)
    {
        try {
            $object = Chatbot::find($id);
            if (!$object) {
                return $this->sendError(__('exception.not_found'));
            }
            return $this->sendResponse($object->load(['type', 'admin', 'category', 'subCategory']));
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function updateChatBot($id, Request $request)
    {
        $admin = $request->user();
        $validator = Validator::make($request->all(), [
            'cat_id' => 'required|integer|exists:chatbot_categories,id',
            'sub_cat_id' => 'required|integer|exists:chatbot_sub_categories,id',
            'link_page' => 'nullable|url',
            'question_en' => 'required|string|max:200',
            'answer_en' => 'required|string',
            'question_vi' => 'required|string|max:200',
            'answer_vi' => 'required|string',
            'status' => 'required|in:enable,disable',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $object = Chatbot::with('type')->find($id);
        if (!$object) {
            return $this->sendError(__('exception.not_found'));
        }

        //check sub cat, cat
        $subCategory = ChatbotSubCategory::find($request->sub_cat_id);
        if (!$subCategory || $subCategory->cat_id != $request->cat_id) {
            return $this->sendError(__('exception.not_found'));
        }

        DB::beginTransaction();
        try {

            $data = $request->only(['type_id', 'cat_id', 'sub_cat_id', 'link_page', 'question_en', 'answer_en', 'question_vi', 'answer_vi', 'status']);
            $data['admin_id'] = $admin->id;

            $object->update($data);

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_CHATBOT,
                'action' => 'Update ' . $object->type->name
            ]);

            DB::commit();

            return $this->sendResponse($object->load(['type', 'admin','category', 'subCategory']));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function updateStatusChatBot($id, Request $request)
    {
        $admin = $request->user();
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:enable,disable'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $object = Chatbot::with('type')->find($id);
        if (!$object) {
            return $this->sendError(__('exception.not_found'));
        }

        $status = $request->status ?? '';
        if ($status == $object->status) {
            return $this->sendResponse(true, "Status updated successfully.");
        }

        DB::beginTransaction();
        try {
            $data = [
                'admin_id' => $admin->id,
                'status' => $status,
            ];

            $object->update($data);

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_CHATBOT,
                'action' => "Update {$object->type->name} Status: " . ucfirst($status)
            ]);
            DB::commit();

            return $this->sendResponse(true, "Status changed successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function destroyChatBot($id, Request $request)
    {
        $admin = $request->user();
        $object = Chatbot::with('type')->find($id);
        if (!$object) {
            return $this->sendError(__('exception.not_found'));
        }

        DB::beginTransaction();
        try {

            $object->delete();

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_CHATBOT,
                'action' => 'Delete '. $object->type->name
            ]);

            DB::commit();

            return $this->sendResponse(true,"success");
        } catch (Exception $ex) {
            DB::rollBack();
            return $this->sendError($ex->getMessage());
        }
    }

}
