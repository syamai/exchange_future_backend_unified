<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\MasterdataService;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\DB;

/**
 * Class MasterdataAPIController
 * @package App\Http\Controllers\API
 */
class MasterdataAPIController extends AppBaseController
{

    public function index()
    {
        return $this->sendResponse(MasterdataService::getAllData());
    }

    public function getAssets()
    {
        $coins = MasterdataService::getOneTable('coins');
        $result = [];
        foreach ($coins as $coin) {
            $confirmationData = $this->getCoinConfirmation($coin->coin);
            $withdrawalLimit = $this->getWithdrawalLimit($coin->coin);
            $feeSetting = $this->getFeeSetting($coin->coin);
            $result[strtoupper($coin->coin)] = [
                'name' => $coin->name,
                'unified_cryptoasset_id' => $coin->id,
                'can_withdraw' => $confirmationData->is_withdraw === 1,
                'can_deposit' => $confirmationData->is_deposit === 1,
                'min_withdraw' => $withdrawalLimit['min_withdraw'],
                'max_withdraw' => $withdrawalLimit['max_withdraw'],
                'maker_fee' => BigNumber::new($feeSetting->fee_maker)->div(100)->toString(),
                'taker_fee' => BigNumber::new($feeSetting->fee_taker)->div(100)->toString(),
            ];
        }
        return $this->sendResponse($result);
    }

    private function getCoinConfirmation($coin)
    {
        $data = MasterdataService::getOneTable('coins_confirmation');
        foreach ($data as $row) {
            if ($row->coin === $coin) {
                return $row;
            }
        }
    }

    private function getWithdrawalLimit($coin)
    {
        $allLimits = MasterdataService::getOneTable('withdrawal_limits');
        $coinLimits = $allLimits->filter(function ($limit) use ($coin) {
            return $limit->currency === $coin;
        })
        ->values()
        ->all();
        return [
            'min_withdraw' => $coinLimits[1]->limit,
            'max_withdraw' => $coinLimits[count($coinLimits) - 1]->limit,
        ];
    }

    private function getFeeSetting($coin)
    {
        return DB::table('market_fee_setting')->where('coin', $coin)->first();
    }
}
