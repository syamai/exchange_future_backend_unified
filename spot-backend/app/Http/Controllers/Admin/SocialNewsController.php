<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\SocialNewsRequest;
use App\Models\NewsNotification;
use App\Models\NewsNotificationCategory;
use App\Models\SocialNew;
use App\Utils;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SocialNewsController extends AppBaseController
{

    public function getListSocialNews(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $data = SocialNew::where('is_pin', 0)
            ->filter($input)
            ->orderBy('updated_at', 'desc')
            ->paginate($limit);

        return $this->sendResponse($data);
    }

    public function getListPinSocialNews(Request $request)
    {
        $input = $request->all();
        $data = SocialNew::where('is_pin', 1)
            ->filter($input)
            ->orderBy('updated_at', 'desc')
            ->get();

        return $this->sendResponse($data);
    }

    public function storeSocialNews(SocialNewsRequest $request)
    {
        $admin = $request->user();

        $data = $request->only(['link_page', 'thumbnail_url', 'title_en', 'content_en', 'title_vi', 'content_vi', 'status']);
        $linkPage = $request->link_page ?? '';
        $host = parse_url($linkPage, PHP_URL_HOST);
        $domain = $this->getRootDomain($host);

        $thumbnailUrl = $request->thumbnail_url;
        if (is_file($thumbnailUrl)) {
            $thumbnailUrl = Utils::saveFileToStorage($thumbnailUrl, 'social', null, 'public');
            $data['thumbnail_url'] = $thumbnailUrl;
        }

        $data['admin_id'] = $admin->id;
        $data['domain_name'] = $domain;

        DB::beginTransaction();
        try {

            $object = SocialNew::create($data);

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_SOCIAL_NEWS,
                'action' => 'Create new Press Releases'
            ]);
            DB::commit();

            return $this->sendResponse($object->refresh());
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getSocialNews($id)
    {
        try {
            $data = SocialNew::find($id);
            if (!$data) {
                return $this->sendError(__('exception.not_found'));
            }
            return $this->sendResponse($data->load(['admin:id,name,email,role']));
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function updateSocialNews($id, SocialNewsRequest $request)
    {
        $admin = $request->user();
        $socialNew = SocialNew::find($id);
        if (!$socialNew) {
            return $this->sendError(__('exception.not_found'));
        }

        DB::beginTransaction();
        try {
            $data = $request->only(['link_page', 'thumbnail_url', 'title_en', 'content_en', 'title_vi', 'content_vi', 'status']);

            $thumbnailUrl = $request->thumbnail_url;
            if (is_file($thumbnailUrl)) {
                $thumbnailUrl = Utils::saveFileToStorage($thumbnailUrl, 'social', null, 'public');
                $data['thumbnail_url'] = $thumbnailUrl;
            }

            $linkPage = $request->link_page ?? '';
            $host = parse_url($linkPage, PHP_URL_HOST);
            $domain = $this->getRootDomain($host);

            $data['admin_id'] = $admin->id;
            $data['domain_name'] = $domain;

            $socialNew->update($data);

            //log activity
            $socialNew->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_SOCIAL_NEWS,
                'action' => 'Update Press Releases'
            ]);
            DB::commit();

            $socialNew->refresh();

            return $this->sendResponse($socialNew->load(['admin:id,name,email,role']));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function updateStatusSocialNews($id, Request $request)
    {
        $admin = $request->user();
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:posted,hidden'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $socialNew = SocialNew::find($id);
        if (!$socialNew) {
            return $this->sendError(__('exception.not_found'));
        }

        $status = $request->status ?? '';
        if ($status == $socialNew->status) {
            return $this->sendResponse(true, "Status updated successfully.");
        }

        DB::beginTransaction();
        try {
            $data = [
                'status' => $status,
                'admin_id' => $admin->id
            ];

            $socialNew->update($data);

            //log activity
            $socialNew->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_SOCIAL_NEWS,
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

    public function destroySocialNews($id, Request $request)
    {
        $admin = $request->user();

        $object = SocialNew::find($id);
        if (!$object) {
            return $this->sendError(__('exception.not_found'));
        }

        DB::beginTransaction();
        try {

            $object->delete();

            //log activity
            $object->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_SOCIAL_NEWS,
                'action' => 'Delete Press Releases'
            ]);

            DB::commit();
            return $this->sendResponse(true, "success");
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function pinSocialNews($id, Request $request)
    {
        $admin = $request->user();
        $socialNew = SocialNew::find($id);
        if (!$socialNew) {
            return $this->sendError(__('exception.not_found'));
        }

        if (1 == $socialNew->is_pin) {
            return $this->sendResponse(true, "Pin successfully.");
        }

        // check count pin
        $countPin = SocialNew::where('is_pin', 1)
            ->count();
        if ($countPin >= Consts::SOCIAL_MAX_PIN) {
            return $this->sendError(__('exception.max_pin'));
        }

        DB::beginTransaction();
        try {
            $data = [
                'is_pin' => 1,
                'admin_id' => $admin->id
            ];

            $socialNew->update($data);

            //log activity
            $socialNew->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_SOCIAL_NEWS,
                'action' => 'Pin Press Releases'
            ]);
            DB::commit();

            return $this->sendResponse(true, "Pin changed successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function unpinSocialNews($id, Request $request)
    {
        $admin = $request->user();
        $socialNew = SocialNew::find($id);
        if (!$socialNew) {
            return $this->sendError(__('exception.not_found'));
        }

        if (1 != $socialNew->is_pin) {
            return $this->sendResponse(true, "Unpin successfully.");
        }

        DB::beginTransaction();
        try {
            $data = [
                'is_pin' => 0,
                'admin_id' => $admin->id
            ];

            $socialNew->update($data);

            //log activity
            $socialNew->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_SOCIAL_NEWS,
                'action' => 'Unpin Press Releases'
            ]);
            DB::commit();

            return $this->sendResponse(true, "Unpin changed successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    private function getRootDomain($host) {
        $parts = explode('.', $host);
        $count = count($parts);

        if ($count >= 2) {
            return $parts[$count - 2] . '.' . $parts[$count - 1];
        }

        return $host;
    }

    public function getCountPinSocialNews(Request $request)
    {
        $countPin = SocialNew::where('is_pin', 1)
            ->count();

        return $this->sendResponse([
            'count' => $countPin,
            'max_count' => Consts::SOCIAL_MAX_PIN
        ]);

    }

}
