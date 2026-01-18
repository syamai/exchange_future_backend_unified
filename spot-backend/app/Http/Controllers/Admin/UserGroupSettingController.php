<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Services\UserGroupSettingService;
use App\Http\Requests\UserGroupSettingRequest;

class UserGroupSettingController extends AppBaseController
{
    private $userGroupSettingService;

    public function __construct(UserGroupSettingService $userGroupSettingService)
    {
        $this->userGroupSettingService = $userGroupSettingService;
    }

    public function getList(Request $request)
    {
        $data = $this->userGroupSettingService->getList($request->all());
        return $this->sendResponse($data);
    }

    public function addNew(UserGroupSettingRequest $request)
    {
        $name = $request->name;
        $memo = $request->memo;

        try {
            $this->userGroupSettingService->addNew([
                'name' => $name,
                'memo' => $memo
            ]);
            return $this->sendResponse('', __('common.create.success'));
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function update(UserGroupSettingRequest $request)
    {
        $params = [
            'name' => $request->name,
            'memo' => $request->memo
        ];
        try {
            $this->userGroupSettingService->update($request->input('id'), $params);
            return $this->sendResponse('', __('common.update.success'));
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function remove($id)
    {
        try {
            $this->userGroupSettingService->remove($id);
            return $this->sendResponse('', __('common.delete.success'));
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
}
