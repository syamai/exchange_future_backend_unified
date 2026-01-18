<?php
/**
 * Created by PhpStorm.
 * Date: 7/19/19
 * Time: 11:02 AM
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UserDeviceController extends AppBaseController
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function getDeviceRegister(Request $request): JsonResponse
    {
        $userId = $request->userId;

        try {
            $data = $this->userService->getDeviceRegister($userId);
            return $this->sendResponse($data);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function deleteDevice(Request $request, $userId, $id): JsonResponse
    {
        //$userId = $request->userId;

        try {
            $result = $this->userService->deleteDevice($userId, $id);
            return $this->sendResponse(array('result' => $result));
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }
}
