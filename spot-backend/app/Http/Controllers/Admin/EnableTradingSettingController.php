<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\EnableTradingSettingService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Validator;

class EnableTradingSettingController extends AppBaseController
{
    private $enableTradingSettingService;

    public function __construct(EnableTradingSettingService $enableTradingSettingService)
    {
        $this->enableTradingSettingService = $enableTradingSettingService;
    }

    public function index(Request $request)
    {
        $data = $this->enableTradingSettingService->getEnableTradingSetting($request->all());
        return $this->sendResponse($data);
    }

    public function getUserListSetting(Request $request)
    {
        $data = $this->enableTradingSettingService->getUserListSetting($request->all());
        return $this->sendResponse($data);
    }

    public function updateUserSetting(Request $request)
    {
        $inputs = $request->all();
        $validator = Validator::make($inputs, [
            'email' => 'required|email',
            'coin' => 'required|string',
            'currency' => 'required|string',
            'enable_trading' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        $id = $request->input('id', null);
        $inputs['id'] = $id;

        DB::beginTransaction();
        try {
            $enableTrading = $this->enableTradingSettingService->createEnableTradingSetting($inputs);
            DB::commit();

            return $this->sendResponse($enableTrading);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function updateCoinSetting(Request $request)
    {
        $inputs = $request->all();
        $validator = Validator::make($inputs, [
            'coin' => 'required|string',
            'currency' => 'required|string',
            'is_enable' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        DB::beginTransaction();
        try {
            $coinSetting = $this->enableTradingSettingService->updateCoinSetting($inputs);
            DB::commit();

            return $this->sendResponse($coinSetting);
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
            'currency' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        if (!User::where('email', $request->get('email'))->exists()) {
            return $this->sendError(__('enable_trading.user_not_exist'));
        }

        $email = $request->get('email', '');
        $currency = $request->get('currency', '');
        $coin = $request->get('coin', '');
        $allMarket = $request->get('allMarket', '');
        $inThisMarket = $request->get('inThisMarket', '');

        if (!$allMarket && !$inThisMarket) {
            $exist = $this->enableTradingSettingService->checkExistEnable($currency, $coin, $email);
            if ($exist) {
                return $this->sendError(__('enable_trading.user_was_exist'));
            }
        }

        DB::beginTransaction();
        try {
            $enableTrading = $this->enableTradingSettingService->createEnableTradingSetting($inputs);

            DB::commit();
            return $this->sendResponse($enableTrading);
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
            $res = $this->enableTradingSettingService->deleteEnableTradingSetting($inputs, $id);

            DB::commit();
            return $this->sendResponse($res);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
}
