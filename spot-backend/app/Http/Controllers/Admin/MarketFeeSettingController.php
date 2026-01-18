<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\MasterdataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Consts;
use App\Http\Services\MarketFeeSettingService;
use Illuminate\Support\Facades\DB;
use App\Models\MarketFeeSetting;

class MarketFeeSettingController extends AppBaseController
{
    private $marketFeeSettingService;

    public function __construct(MarketFeeSettingService $marketFeeSettingService)
    {
        $this->marketFeeSettingService = $marketFeeSettingService;
    }

    public function index(Request $request)
    {
        $data = $this->marketFeeSettingService->getMarketFeeSetting($request->all());
        return $this->sendResponse($data);
    }

    public function update(Request $request)
    {
        $id = $request->input('id', null);

        if ($id == null) {
            DB::beginTransaction();
            try {
                $marketFee = MarketFeeSetting::create([
                    'currency' => $request->input('currency', ''),
                    'coin' => $request->input('coin', ''),
                    'fee_taker' => $request->input('fee_taker', 0),
                    'fee_maker' => $request->input('fee_maker', 0)
                ]);
                MasterdataService::clearCacheOneTable('market_fee_setting');
                DB::commit();
                return $this->sendResponse($marketFee);
            } catch (\Exception $ex) {
                DB::rollBack();
                Log::error($ex);
                return $this->sendError($ex->getMessage());
            }
        } else {
            $data = $this->marketFeeSettingService->updateMarketFeeSetting($request->all(), $id);
            MasterdataService::clearCacheOneTable('market_fee_setting');
            return $this->sendResponse($data);
        }
    }
}
