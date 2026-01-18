<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Services\UserGroupService;
use App\Http\Requests\UserGroupRequest;

class UserGroupController extends AppBaseController
{
    private $userGroupService;

    public function __construct(UserGroupService $userGroupService)
    {
        $this->userGroupService = $userGroupService;
    }

    public function getList(Request $request)
    {
        $data = $this->userGroupService->getList($request->all());
        return $this->sendResponse($data);
    }

    public function update(Request $request)
    {
        try {
            $this->userGroupService->update($request->lst_user, $request->lst_group);
            return $this->sendResponse('', __('common.update.success'));
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }

    public function delete(Request $request)
    {
        try {
            $this->userGroupService->delete($request->lst_remove);
            return $this->sendResponse('', __('common.delete.success'));
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }
}
