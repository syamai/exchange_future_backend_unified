<?php
namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Services\InstrumentService;
use App\Http\Controllers\AppBaseController;
use App\Models\AutoDividendSetting;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class InstrumentController extends AppBaseController
{
    protected $instrumentService ;
    public function __construct()
    {
        $this->instrumentService = new InstrumentService();
    }
    public function getInstruments(Request $request): JsonResponse
    {
        $params = $request->all();
        try {
            $getInstruments = $this->instrumentService->getInstruments($params);
            return $this->sendResponse($getInstruments);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }
    public function getInstrumentsSettings(Request $request): JsonResponse
    {
        $params = $request->all();
        try {
            $getInstrumentsSettings = $this->instrumentService->getInstrumentsSettings();
            return $this->sendResponse($getInstrumentsSettings);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }
    public function updateInstruments(Request $request): JsonResponse
    {
        $params = $request->all();
        $validator = $this->checkValidate($params);
        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        };

        try {
            $updateInstruments = $this->instrumentService->updateInstruments($params);
            if ($updateInstruments) {
                $stateSettings = array(
                    'open' => 1,
                    'pending' => 0,
                    'close' => 0,
                );

                $state = $stateSettings[strtolower($updateInstruments->state)];
                $symbol = $updateInstruments->symbol;

                AutoDividendSetting::where('coin', $updateInstruments->symbol)->update(['is_show' => $state, 'coin' => $symbol]);
            }

            return $this->sendResponse(true);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }
    public function createInstruments(Request $request): JsonResponse
    {
        $params = $request->all();
        $validator = $this->checkValidate($params);
        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        };

        try {
            $createInstruments = $this->instrumentService->createInstruments($params);

            AutoDividendSetting::create([
                'enable' => false,
                'market' => strtolower($params["root_symbol"]),
                'coin' => strtoupper($params["symbol"]),
                'time_from' => null,
                'time_to' => null,
                'payfor' => Consts::TYPE_MAIN_BALANCE,
                'payout_coin' => 'AMAL',
                'payout_amount' => 0,
                'setting_for' => 'margin',
                'lot' => 0
            ]);
            return $this->sendResponse($createInstruments);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }
    public function deleteInstruments($id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $deleteInstruments = $this->instrumentService->deleteInstruments($id);
            AutoDividendSetting::where('coin', $deleteInstruments->symbol)->update(['is_show' => 0]);
            DB::commit();
            return $this->sendResponse(true);
        } catch (HttpException $e) {
            Log::error($e);
            DB::rollBack();
            return $this->sendError($e->getMessage());
        }
    }
    public function getCoinActive(): JsonResponse
    {
        try {
            $getCoinActive = $this->instrumentService->getCoinActive();
            return $this->sendResponse($getCoinActive);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }
    public function getIndexCoinActive(Request $request): JsonResponse
    {
        $params = $request->all();
        try {
            $getIndexCoinActive = $this->instrumentService->getIndexCoinActive($params);
            return $this->sendResponse($getIndexCoinActive);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function checkValidate($params)
    {
        $id = request('id', -1);
        return $validator = Validator::make($params, [
            'symbol' => 'required|unique:instruments,symbol,'.$id.',id',
            'root_symbol' => 'required',
            'state' => 'required',
            'type' => 'required',
            'expiry' => 'instrument_check_require_future',
            'base_underlying' => 'required',
            'quote_currency' => 'required|instrument_check_difficult_than:base_underlying',
            'underlying_symbol' => 'required',
            'init_margin' => 'required|numeric|min:0|max:1',
            'maint_margin' => 'required|numeric|min:0|instrument_check_small_than_init_margin',
            'maker_fee' => 'required|numeric|min:-1|max:1',
            'taker_fee' => 'required|numeric|min:-1|max:1|fee_condition',
            'settlement_fee' => 'required|numeric|settlement_fee_condition|max:1',
            'reference_index' => 'required|instrument_check_refe_index',
            'funding_base_index' => 'instrument_check_funding_base',
            'funding_quote_index' => 'instrument_check_funding_quote',
            'funding_premium_index' => 'instrument_check_require_perpetual',
            'tick_size' => 'required|numeric|min:0',
            'max_price' => 'required|numeric|min:0',
            'max_order_qty' => 'required|numeric|min:0',
            'multiplier' => 'required|numeric|min:-1|max:1',
            'risk_limit' => 'required|numeric|min:0',
            'risk_step' => 'required|numeric|min:0',
        ]);
    }

    public function getInstrumentDropdownData($params): JsonResponse
    {
        try {
            $dataDropDown = $this->instrumentService->getInstrumentsSettings();
            return $this->sendResponse($dataDropDown);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }
}
