<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Models\NewsNotification;
use App\Models\NewsNotificationCategory;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NewsNotificationController extends AppBaseController
{

    public function getCategories(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', 0);
        $data = NewsNotificationCategory::filter($input)
            ->orderBy('created_at', 'desc')
            ->when($limit > 0, function ($query) use ($limit) {
                return $query->paginate($limit);
            }, function ($query) {
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
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->errors());
            }

            $catData = $request->only(['title_en', 'title_vi']);
            $catData['status'] = 'enable';
            $category = NewsNotificationCategory::create($catData);

            return $this->sendResponse($category);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getCategory($id)
    {
        try {
            $category = NewsNotificationCategory::find($id);
            if (!$category) {
                return $this->sendError(__('exception.not_found'));
            }
            return $this->sendResponse($category);
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
                'status' => 'nullable|in:disable,enable'
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->errors());
            }

            $category = NewsNotificationCategory::find($id);
            if (!$category) {
                return $this->sendError(__('exception.not_found'));
            }

            $catData = $request->only(['title_en', 'title_vi', 'status']);
            $category->update($catData);

            $category->refresh();
            return $this->sendResponse($category);
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function destroyCategory($id)
    {
        try {

            $category = NewsNotificationCategory::find($id);
            if (!$category) {
                return $this->sendError(__('exception.not_found'));
            }

            if ($category->newsNotifications()->exists()) {
                return $this->sendError(__('exception.cat_used'));
            }

            $category->delete();
            return $this->sendResponse(true, "success");
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }


    public function getNewsNotifications(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $data = NewsNotification::with(['category'])
            ->filter($input)
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        return $this->sendResponse($data);
    }

    public function storeNewsNotifications(Request $request)
    {
        $admin = $request->user();

        $validator = Validator::make($request->all(), [
            'link_event' => 'nullable|url',
            'cat_id' => 'required|integer|exists:news_notification_categories,id',
            'title_en' => 'required|string|max:200',
            'content_en' => 'required|string',
            'title_vi' => 'required|string|max:200',
            'content_vi' => 'required|string',
            'status' => 'required|in:posted,hidden'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $data = $request->only(['cat_id', 'link_event', 'title_en', 'content_en', 'title_vi', 'content_vi', 'status']);
        //check sub cat, cat
        $category = NewsNotificationCategory::find($request->cat_id);
        if (!$category) {
            return $this->sendError(__('exception.cat_not_found'));
        }

        //$data['status'] = Consts::NEWS_NOTIFICATION_STATUS_POSTED;
        $data['admin_id'] = $admin->id;

        DB::beginTransaction();
        try {

            $object = NewsNotification::create($data);

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_NEWS_NOTIFICATION,
                'action' => 'Create ' . Consts::FEATURE_NEWS_NOTIFICATION
            ]);
            DB::commit();

            return $this->sendResponse($object->load(['category']));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getNewsNotification($id)
    {
        try {
            $data = NewsNotification::find($id);
            if (!$data) {
                return $this->sendError(__('exception.not_found'));
            }
            return $this->sendResponse($data->load(['category', 'admin:id,name,email,role']));
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function updateNewsNotification($id, Request $request)
    {
        $admin = $request->user();
        $validator = Validator::make($request->all(), [
            'cat_id' => 'required|integer|exists:news_notification_categories,id',
            'title_en' => 'required|string|max:200',
            'content_en' => 'required|string',
            'title_vi' => 'required|string|max:200',
            'content_vi' => 'required|string',
            'status' => 'required|in:posted,hidden',
            'link_event' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $newsNotification = NewsNotification::find($id);
        if (!$newsNotification) {
            return $this->sendError(__('exception.not_found'));
        }

        DB::beginTransaction();
        try {
            $data = $request->only(['cat_id', 'link_event', 'title_en', 'content_en', 'title_vi', 'content_vi', 'status']);
            $data['admin_id'] = $admin->id;

            $newsNotification->update($data);

            //log activity
            $newsNotification->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_NEWS_NOTIFICATION,
                'action' => 'Update ' . Consts::FEATURE_NEWS_NOTIFICATION
            ]);
            DB::commit();

            $newsNotification->refresh();

            return $this->sendResponse($newsNotification->load(['category', 'admin:id,name,email,role']));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function updateStatusNewsNotification($id, Request $request)
    {
        $admin = $request->user();
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:posted,hidden'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $newsNotification = NewsNotification::find($id);
        if (!$newsNotification) {
            return $this->sendError(__('exception.not_found'));
        }

        $status = $request->status ?? '';
        if ($status == $newsNotification->status) {
            return $this->sendResponse(true, "Status updated successfully.");
        }

        DB::beginTransaction();
        try {
            $data = [
                'status' => $status,
                'admin_id' => $admin->id
            ];

            $newsNotification->update($data);

            //log activity
            $newsNotification->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_NEWS_NOTIFICATION,
                'action' => 'Update Status:' . ucfirst($status)
            ]);
            DB::commit();

            return $this->sendResponse(true, "Status changed successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function destroyNewsNotification($id, Request $request)
    {
        $admin = $request->user();

        $object = NewsNotification::find($id);
        if (!$object) {
            return $this->sendError(__('exception.not_found'));
        }

        DB::beginTransaction();
        try {

            $object->delete();

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_NEWS_NOTIFICATION,
                'action' => 'Delete ' . Consts::FEATURE_NEWS_NOTIFICATION
            ]);

            DB::commit();
            return $this->sendResponse(true, "success");
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

}
