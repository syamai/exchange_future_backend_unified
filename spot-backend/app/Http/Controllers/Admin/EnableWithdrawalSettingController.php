<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\EnableWithdrawalSettingService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Validator;

class EnableWithdrawalSettingController extends AppBaseController
{
    private $enableWithdrawalSettingService;

    public function __construct(EnableWithdrawalSettingService $enableWithdrawalSettingService)
    {
        $this->enableWithdrawalSettingService = $enableWithdrawalSettingService;
    }

    public function index(Request $request)
    {
        $data = $this->enableWithdrawalSettingService->getEnableWithdrawalSetting($request->all());
        return $this->sendResponse($data);
    }

    public function getWithdrawSetting(Request $request)
    {
        $params = $request->all();
        $params['email'] = $request->user()->email;
        $data = $this->enableWithdrawalSettingService->getWithdrawSetting($params);
        return $this->sendResponse($data);
    }

    public function getUserListSetting(Request $request)
    {
        $data = $this->enableWithdrawalSettingService->getUserListSetting($request->all());
        return $this->sendResponse($data);
    }

    public function updateUserSetting(Request $request)
    {
        $inputs = $request->all();
        $validator = Validator::make($inputs, [
            'email' => 'required|email',
            'coin' => 'required|string',
            'enable_withdrawal' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        $id = $request->input('id', null);
        $enableFee = @$inputs['enable_withdrawal'] ?? true;
        $enableFee = $enableFee ? Consts::ENABLE_WITHDRAWAL : Consts::DISABLE_WITHDRAWAL;
        $inputs['enable_withdrawal'] = $enableFee;
        $inputs['id'] = $id;

        DB::beginTransaction();
        try {
            if ($id == null) {
                $enableFee = $this->enableWithdrawalSettingService->createEnableWithdrawalSetting($inputs, $id);
            } else {
                $enableFee = $this->enableWithdrawalSettingService->updateEnableWithdrawalSetting($inputs, $id);
            }
            DB::commit();

            return $this->sendResponse($enableFee);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function addUserSetting(Request $request)
    {
        $inputs = $request->all();
        $validator = Validator::make($inputs, [
            'email' => 'required|email',
            'coin' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        if (!User::where('email', $request->get('email'))->exists()) {
            return $this->sendError(__('enable_withdrawal.user_not_exist'));
        }

        $email = $request->get('email', '');
        $coin = $request->get('coin', '');
        $allCoins = $request->get('allCoins', '');

        if (!$allCoins) {
            $exist = $this->enableWithdrawalSettingService->checkExistEnable($coin, $email);
            if ($exist) {
                return $this->sendError(__('enable_withdrawal.user_was_exist'));
            }
        }

        $enableFee = @$inputs['enable_withdrawal'] ?? true;
        $enableFee = $enableFee ? Consts::ENABLE_WITHDRAWAL : Consts::DISABLE_WITHDRAWAL;
        $inputs['enable_withdrawal'] = $enableFee;

        DB::beginTransaction();
        try {
            $enableFee = $this->enableWithdrawalSettingService->createEnableWithdrawalSetting($inputs, null);

            DB::commit();
            return $this->sendResponse($enableFee);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function deleteUserSetting(Request $request, $id)
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
            $res = $this->enableWithdrawalSettingService->deleteEnableWithdrawalSetting($inputs, $id);

            DB::commit();
            return $this->sendResponse($res);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
}
