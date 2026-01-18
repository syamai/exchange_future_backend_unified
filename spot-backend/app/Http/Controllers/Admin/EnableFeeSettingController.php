<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\EnableFeeSettingService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Validator;

class EnableFeeSettingController extends AppBaseController
{
    private EnableFeeSettingService $enableFeeSettingService;

    public function __construct(EnableFeeSettingService $enableFeeSettingService)
    {
        $this->enableFeeSettingService = $enableFeeSettingService;
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->enableFeeSettingService->getEnableFeeSetting($request->all());
        return $this->sendResponse($data);
    }

    public function getUserListSetting(Request $request): JsonResponse
    {
        $data = $this->enableFeeSettingService->getUserListSetting($request->all());
        return $this->sendResponse($data);
    }

    public function updateUserSetting(Request $request): JsonResponse
    {
        $inputs = $request->all();
        $validator = Validator::make($inputs, [
            'email' => 'required|email',
            'coin' => 'required|string',
            'currency' => 'required|string',
            'enable_fee' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        $id = $request->input('id', null);
        $enableFee = @$inputs['enable_fee'] ?? true;
        $enableFee = $enableFee ? Consts::ENABLE_FEE : Consts::DISABLE_FEE;
        $inputs['enable_fee'] = $enableFee;
        $inputs['id'] = $id;

        DB::beginTransaction();
        try {
            if ($id == null) {
                $enableFee = $this->enableFeeSettingService->createEnableFeeSetting($inputs, $id);
            } else {
                $enableFee = $this->enableFeeSettingService->updateEnableFeeSetting($inputs, $id);
            }
            DB::commit();

            return $this->sendResponse($enableFee);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function addUserSetting(Request $request): JsonResponse
    {
        $inputs = $request->all();
        $validator = Validator::make($inputs, [
            'email' => 'required|email',
            'coin' => 'required|string',
            'currency' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        if (!User::where('email', $request->get('email'))->exists()) {
            return $this->sendError(__('enable_fee.user_not_exist'));
        }

        $email = $request->get('email', '');
        $currency = $request->get('currency', '');
        $coin = $request->get('coin', '');
        $allMarket = $request->get('allMarket', '');
        $inThisMarket = $request->get('inThisMarket', '');

        if (!$allMarket && !$inThisMarket) {
            $exist = $this->enableFeeSettingService->checkExistEnable($currency, $coin, $email);
            if ($exist) {
                return $this->sendError(__('enable_fee.user_was_exist'));
            }
        }

        $enableFee = @$inputs['enable_fee'] ?? true;
        $enableFee = $enableFee ? Consts::ENABLE_FEE : Consts::DISABLE_FEE;
        $inputs['enable_fee'] = $enableFee;

        DB::beginTransaction();
        try {
            $enableFee = $this->enableFeeSettingService->createEnableFeeSetting($inputs, null);

            DB::commit();
            return $this->sendResponse($enableFee);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function deleteUserSetting(Request $request, $id): JsonResponse
    {
        $inputs = $request->all();
        $validator = Validator::make($inputs, [
            'id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        $id = $inputs['id'];

        DB::beginTransaction();
        try {
            $res = $this->enableFeeSettingService->deleteEnableFeeSetting($inputs, $id);

            DB::commit();
            return $this->sendResponse($res);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
}
