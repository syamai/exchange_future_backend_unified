<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\CoinSetting;
use App\Models\FeeLevel;
use App\Models\WithdrawalLimit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class SettingController extends AppBaseController
{
    public function getTransactionUnits(Request $request)
    {
        $keyword = $request->input('keyword', '');
        $coinSettings = CoinSetting::select('coin', 'quantity_precision', 'price_precision')
            ->where('coin', 'like', '%' . $keyword . '%')
            ->get()->keyBy('coin');
        return $this->sendResponse($coinSettings);
    }

    public function updateTransactionUnits(Request $request)
    {
        try {
            CoinSetting::where('coin', $request->input('coin'))
                ->update(['quantity_precision' => $request->input('quantityPrecision'), 'price_precision' => $request->input('pricePrecision')]);
            $this->clearCacheTable('coin_settings');
            $coinSettings = CoinSetting::where('coin', $request->input('coin'))->first();
            return $this->sendResponse($coinSettings);
        } catch (\Exception $ex) {
            return $this->sendError($ex);
        }
    }

    public function getTransactionFees()
    {
        $levels = FeeLevel::orderBy('level', 'asc')->get();
        return $this->sendResponse($levels);
    }

    public function updateTransactionFees(Request $request)
    {
        try {
            FeeLevel::where('level', $request->input('level'))
                ->update(['amount' => $request->input('amount'), 'fee' => $request->input('fee')]);
            $this->clearCacheTable('fee_levels');
            $feeLevel = FeeLevel::where('level', $request->input('level'))->first();
            return $this->sendResponse($feeLevel);
        } catch (\Exception $ex) {
            return $this->sendError($ex);
        }
    }

    private function clearCacheTable($table)
    {
        Cache::forget("masterdata_$table");
        Cache::forget('dataVersion');
        Cache::forget('masterdata');
    }

    public function getWithdrawalFees()
    {
        $withdrawalLimits = WithdrawalLimit::select('currency', 'fee', 'minium_withdrawal')->get();
        return $this->sendResponse($withdrawalLimits);
    }

    public function updateWithdrawalFees(Request $request)
    {
        try {
            WithdrawalLimit::where('currency', $request->input('currency'))
                ->update(['minium_withdrawal' => $request->input('minium_withdrawal'), 'fee' => $request->input('fee')]);
            $this->clearCacheTable('withdrawal_limits');
            $withdrawalLimit = WithdrawalLimit::where('currency', $request->input('currency'))->first();
            return $this->sendResponse($withdrawalLimit);
        } catch (\Exception $ex) {
            return $this->sendError($ex);
        }
    }
}
