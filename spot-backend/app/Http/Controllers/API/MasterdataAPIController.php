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
                'can_withdraw' => $confirmationData ? $confirmationData->is_withdraw === 1 : false,
                'can_deposit' => $confirmationData ? $confirmationData->is_deposit === 1 : false,
                'min_withdraw' => $withdrawalLimit['min_withdraw'] ?? '0',
                'max_withdraw' => $withdrawalLimit['max_withdraw'] ?? '0',
                'maker_fee' => $feeSetting ? BigNumber::new($feeSetting->fee_maker)->div(100)->toString() : '0',
                'taker_fee' => $feeSetting ? BigNumber::new($feeSetting->fee_taker)->div(100)->toString() : '0',
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

        if (count($coinLimits) < 2) {
            return [
                'min_withdraw' => '0',
                'max_withdraw' => '0',
            ];
        }

        return [
            'min_withdraw' => $coinLimits[1]->limit ?? '0',
            'max_withdraw' => $coinLimits[count($coinLimits) - 1]->limit ?? '0',
        ];
    }

    private function getFeeSetting($coin)
    {
        return DB::table('market_fee_setting')->where('coin', $coin)->first();
    }
}
