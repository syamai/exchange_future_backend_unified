<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\CircuitBreakerService;
use App\Http\Services\MasterdataService;
use App\Models\CircuitBreakerCoinPairSetting;
use App\Models\OrderTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CircuitBreakerController extends AppBaseController
{
    private CircuitBreakerService $service;

    public function __construct(CircuitBreakerService $service)
    {
        $this->service = $service;
    }

    public function getSetting(): JsonResponse
    {
        $res = $this->service->getSetting();

        return $this->sendResponse($res, $res ? 'ok' : 'fail');
    }

    public function updateSetting(Request $request): JsonResponse
    {
        $params = $request->all();
        $validator = Validator::make($params, [
            'range_listen_time' => 'required|numeric',
            'circuit_breaker_percent' => 'required|numeric',
            'block_time' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        DB::beginTransaction();
        try {
            $res = $this->service->updateSetting($params);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }

        MasterdataService::clearCacheOneTable('circuit_breaker_settings');
        MasterdataService::clearCacheOneTable('circuit_breaker_coin_pair_settings');
        $this->sendSettingEvent();

        return $this->sendResponse($res);
    }

    public function changeStatus(Request $request): JsonResponse
    {
        $params = $request->all();
        $validator = Validator::make($params, [
            'status' => 'required|in:' . Consts::CIRCUIT_BREAKER_STATUS_ENABLE . ',' . Consts::CIRCUIT_BREAKER_STATUS_DISABLE,
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        $status = $request->status;
        DB::beginTransaction();
        try {
            $changeStatus = $this->service->changeStatus($status);
            DB::commit();

            $this->sendSettingEvent();

            return $this->sendResponse($changeStatus);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function sendSettingEvent()
    {
        $setting = $this->service->getSetting();
        $this->service->sendCircuitBreakerSettingUpdatedEvent($setting);
    }

    public function getCoinPairSetting(Request $request): JsonResponse
    {
        $res = $this->service->getCoinPairSettings($request->all());

        return $this->sendResponse($res, $res ? 'ok' : 'fail');
    }

    public function updateCoinPairSetting(Request $request): JsonResponse
    {
        // Sample data:
        // id: 7
        // coin: "xrp"
        // currency: "btc"
        // block_time: "1"
        // range_listen_time: "1"
        // circuit_breaker_percent: "1"
        // created_at: "2019-08-22 08:29:39"
        // updated_at: "2019-08-22 08:29:39"
        // status: "enable"

        $params = $request->all();
        $validator = Validator::make($params, [
            'coin' => 'required|string',
            'currency' => 'required|string',
            'block_time' => 'required|numeric',
            'range_listen_time' => 'required|numeric',
            'circuit_breaker_percent' => 'required|numeric',
            'status' => 'required|in:'.Consts::CIRCUIT_BREAKER_STATUS_ENABLE.','.Consts::CIRCUIT_BREAKER_STATUS_DISABLE,
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        $id = $request->input('id', null);
        $coin = $request->input('coin', '');
        $currency = $request->input('currency', '');

        DB::beginTransaction();
        try {
            if ($id == null) {
                $coinPairSetting = CircuitBreakerCoinPairSetting::where('currency', $currency)->where('coin', $coin)->first();
                if ($coinPairSetting) {
                    $coinPairSetting->update([
                        'block_time' => $request->input('block_time', 0),
                        'range_listen_time' => $request->input('range_listen_time', 0),
                        'circuit_breaker_percent' => $request->input('circuit_breaker_percent', 0),
                        'status' => $request->input('status', Consts::CIRCUIT_BREAKER_BLOCK_TRADING_STATUS),
                    ]);
                } else {
                    $coinPairSetting = CircuitBreakerCoinPairSetting::create([
                        'coin' => $request->input('coin', ''),
                        'currency' => $request->input('currency', ''),
                        'block_time' => $request->input('block_time', 0),
                        'range_listen_time' => $request->input('range_listen_time', 0),
                        'circuit_breaker_percent' => $request->input('circuit_breaker_percent', 0),
                        'status' => $request->input('status', Consts::CIRCUIT_BREAKER_BLOCK_TRADING_STATUS),
                        'last_order_transaction_id' => @OrderTransaction::orderBy('id', 'desc')->first()->id,
                    ]);
                }
                $res = $this->sendResponse($coinPairSetting);
            } else {
                if ($request->get('status', '') == Consts::CIRCUIT_BREAKER_STATUS_ENABLE) {
                    $params['locked_at'] = null;
                    $params['unlocked_at'] = null;
                }
                $data = $this->service->updateCoinPairSetting($params, $id);
                $res = $data ? $this->sendResponse($data) : $this->sendError(__('circuit_breaker_setting.update_fail'));
            }
            DB::commit();
            MasterdataService::clearCacheOneTable('circuit_breaker_coin_pair_settings');

            // Send notification to enable/disable trading
            $coinPairSetting = $this->service->getCoinPairSetting($currency, $coin);
            $this->service->sendCoinPairSettingUpdatedEvent($coinPairSetting);

            return $res;
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
}
