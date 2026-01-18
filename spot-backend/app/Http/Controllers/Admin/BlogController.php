<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\BlogRequest;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Utils;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BlogController extends AppBaseController
{

    public function getCategories(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', 0);
        $data = BlogCategory::with(['admin'])
            ->filter($input)
            ->orderBy('created_at', 'desc')
            ->when($limit > 0, function ($query) use ($limit){
                return $query->paginate($limit);
            }, function ($query){
                return $query->where('status', Consts::ENABLE_STATUS)->get();
            });


        return $this->sendResponse($data);
    }

    public function storeCategories(Request $request)
    {
        $admin = $request->user();
        $validator = Validator::make($request->all(), [
            'title_en' => 'required|string|max:200',
            'title_vi' => 'required|string|max:200',
            'status' => 'required|in:enable,disable'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        DB::beginTransaction();
        try {

            $catData = $request->only(['title_en', 'title_vi', 'status']);
            $catData['admin_id'] = $admin->id;
            $category = BlogCategory::create($catData);

            //log activity
            $category->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_BLOG,
                'action' => 'Create new Category'
            ]);

            DB::commit();
            return $this->sendResponse($category->load('admin'));
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
            'status' => 'required|in:disable,enable'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $category = BlogCategory::find($id);
        if (!$category) {
            return $this->sendError(__('exception.not_found'));
        }

        DB::beginTransaction();
        try {
            $catData = $request->only(['title_en', 'title_vi', 'status']);
            $catData['admin_id'] = $admin->id;
            $category->update($catData);

            //log activity
            $category->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_BLOG,
                'action' => 'Update Category'
            ]);

            $category->refresh();
            DB::commit();

            return $this->sendResponse($category->load('admin'));
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

        $category = BlogCategory::find($id);
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
                'status' => $status,
                'admin_id' => $admin->id,
            ];

            $category->update($data);

            //log activity
            $category->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_BLOG,
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
            $category = BlogCategory::find($id);
            if (!$category) {
                return $this->sendError(__('exception.not_found'));
            }
            return $this->sendResponse($category->load('admin'));
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function destroyCategory($id, Request $request)
    {
        $admin = $request->user();
        $category = BlogCategory::find($id);
        if (!$category) {
            return $this->sendError(__('exception.not_found'));
        }

        if ($category->blogs()->exists()) {
            return $this->sendError(__('exception.cat_used'));
        }

        DB::beginTransaction();
        try {
            $category->delete();

            //log activity
            $category->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_BLOG,
                'action' => 'Delete Category'
            ]);

            DB::commit();

            return $this->sendResponse(true,"success");
        } catch (Exception $ex) {
            DB::rollBack();
            return $this->sendError($ex->getMessage());
        }
    }

    public function getBlogs(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $data = Blog::with(['category', 'admin'])
            ->where('is_pin', 0)
            ->filter($input)
            ->orderBy('updated_at', 'desc')
            ->paginate($limit);

        return $this->sendResponse($data);
    }

    public function getPinBlogs(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $data = Blog::with(['category', 'admin'])
            ->where('is_pin', 1)
            ->filter($input)
            ->orderBy('updated_at', 'desc')
            ->paginate($limit);

        return $this->sendResponse($data);
    }

    public function storeBlog(BlogRequest $request)
    {
        $admin = $request->user();
        $data = $request->only([
            'cat_id',
            'static_url',
            'thumbnail_url',
            'title_en',
            'seo_title_en',
            'meta_keywords_en',
            'seo_description_en',
            'content_en',
            'title_vi',
            'seo_title_vi',
            'meta_keywords_vi',
            'seo_description_vi',
            'content_vi',
            'status',
        ]);

        $thumbnailUrl = $request->thumbnail_url;
        if (is_file($thumbnailUrl)) {
            $thumbnailUrl = Utils::saveFileToStorage($thumbnailUrl, 'blog', null, 'public');
            $data['thumbnail_url'] = $thumbnailUrl;
        }

        $data['admin_id'] = $admin->id;

        DB::beginTransaction();
        try {

            $object = Blog::create($data);

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_BLOG,
                'action' => 'Create new Blog'
            ]);

            DB::commit();

            return $this->sendResponse($object->load(['admin','category']));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getBlog($id)
    {
        try {
            $object = Blog::find($id);
            if (!$object) {
                return $this->sendError(__('exception.not_found'));
            }
            return $this->sendResponse($object->load(['admin', 'category']));
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function updateBlog($id, BlogRequest $request)
    {
        $admin = $request->user();

        $object = Blog::find($id);
        if (!$object) {
            return $this->sendError(__('exception.not_found'));
        }

        $data = $request->only(
            'cat_id',
            'static_url',
            'thumbnail_url',
            'title_en',
            'seo_title_en',
            'meta_keywords_en',
            'seo_description_en',
            'content_en',
            'title_vi',
            'seo_title_vi',
            'meta_keywords_vi',
            'seo_description_vi',
            'content_vi',
            'status',
        );

        $thumbnailUrl = $request->thumbnail_url;
        if (is_file($thumbnailUrl)) {
            $thumbnailUrl = Utils::saveFileToStorage($thumbnailUrl, 'blog', null, 'public');
            $data['thumbnail_url'] = $thumbnailUrl;
        }

        $data['admin_id'] = $admin->id;

        DB::beginTransaction();
        try {

            $object->update($data);

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_BLOG,
                'action' => 'Update Blog'
            ]);

            DB::commit();

            return $this->sendResponse($object->load(['admin','category']));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function updateStatusBlog($id, Request $request)
    {
        $admin = $request->user();
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:posted,hidden'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $object = Blog::find($id);
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
                'feature' => Consts::FEATURE_BLOG,
                'action' => "Update Status: " . ucfirst($status)
            ]);
            DB::commit();

            return $this->sendResponse(true, "Status changed successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function destroyBlog($id, Request $request)
    {
        $admin = $request->user();
        $object = Blog::find($id);
        if (!$object) {
            return $this->sendError(__('exception.not_found'));
        }

        DB::beginTransaction();
        try {

            $object->delete();

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_BLOG,
                'action' => 'Delete Blog'
            ]);

            DB::commit();

            return $this->sendResponse(true,"success");
        } catch (Exception $ex) {
            DB::rollBack();
            return $this->sendError($ex->getMessage());
        }
    }

    public function pinBlog($id, Request $request)
    {
        $admin = $request->user();
        $object = Blog::find($id);
        if (!$object) {
            return $this->sendError(__('exception.not_found'));
        }

        if (1 == $object->is_pin) {
            return $this->sendResponse(true, "Pin successfully.");
        }

        // check count pin
        $countPin = Blog::where('is_pin', 1)
            ->count();
        $maxBlogPin = env('BLOG_MAX_PIN', Consts::BLOG_MAX_PIN);
        if ($maxBlogPin > 0 && $countPin >= $maxBlogPin) {
            return $this->sendError(__('exception.max_pin'));
        }

        DB::beginTransaction();
        try {
            $data = [
                'is_pin' => true,
                'admin_id' => $admin->id
            ];

            $object->update($data);

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_BLOG,
                'action' => 'Pin Blog'
            ]);
            DB::commit();

            return $this->sendResponse(true, "Pin changed successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function unpinBlog($id, Request $request)
    {
        $admin = $request->user();
        $object = Blog::find($id);
        if (!$object) {
            return $this->sendError(__('exception.not_found'));
        }

        if (1 != $object->is_pin) {
            return $this->sendResponse(true, "Unpin successfully.");
        }

        DB::beginTransaction();
        try {
            $data = [
                'is_pin' => false,
                'admin_id' => $admin->id
            ];

            $object->update($data);

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_BLOG,
                'action' => 'Unpin Blog'
            ]);
            DB::commit();

            return $this->sendResponse(true, "Unpin changed successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

}
