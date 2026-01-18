<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\Airdrop\DividendAutoHistoryRequest;
use App\Http\Requests\Airdrop\DividendManualHistoryRequest;
use App\Http\Requests\Airdrop\DividendManualBonusRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\AirdropService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Consts;
use App\Http\Services\AutoDividendService;
use App\Models\AutoDividendSetting;
use Illuminate\Support\Facades\DB;
use App\Http\Services\UserSettingService;
use App\Http\Services\HealthCheckService;

class AirdropAPIController extends AppBaseController
{
    protected mixed $airdropService;
    protected mixed $autoDividendService;
    protected UserSettingService $userSettingService;

    public function __construct()
    {
        $this->airdropService = app(AirdropService::class);
        $this->autoDividendService = app(AutoDividendService::class);
        $this->userSettingService = new UserSettingService();
    }

    public function changeStatus(Request $request)
    {
        $params = $request->all();
        $validator = Validator::make($params, [
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }
        $status = $request->status;
        DB::beginTransaction();
        try {
            $changeStatus = $this->airdropService->changeStatus($status);
            DB::commit();
            return $this->sendResponse($changeStatus);
        } catch (HttpException $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function changeStatusPayFee(Request $request): JsonResponse
    {
        $enable_fee_amal = $request->enable_fee_amal;
        DB::beginTransaction();
        try {
            $changeStatus = $this->airdropService->changeStatusPayFee($enable_fee_amal);
            if ($changeStatus && $enable_fee_amal == false) {
                $this->userSettingService->updateValueByKey('amal_pay', 0);
            }
            DB::commit();
            return $this->sendResponse($changeStatus);
        } catch (HttpException $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function changeStatusEnableWallet(Request $request): JsonResponse
    {
        $status = $request->status;
        DB::beginTransaction();
        try {
            $changeStatus = $this->airdropService->changeStatusEnableWallet($status);
            if ($changeStatus && $status == false) {
                // $this->userSettingService->updateValueByKey('amal_pay', 0);
                $this->userSettingService->updateValueByKey('amal_pay_wallet', 'main');
            }
            DB::commit();
            return $this->sendResponse($changeStatus);
        } catch (HttpException $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }


    public function getAirdropSetting(): JsonResponse
    {
        try {
            $getAirdropSetting = $this->airdropService->getAirdropSetting();
            return $this->sendResponse($getAirdropSetting);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getAirdropSettingToRender(Request $request): JsonResponse
    {
        $timezone = $request->all();
        try {
            $getAirdropSetting = $this->airdropService->getAirdropSettingToRender($timezone[0]);
            return $this->sendResponse($getAirdropSetting);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getAutoDividendSetting(Request $request): JsonResponse
    {
        $params = $request->all();
        $getAutoDividendSetting = $this->autoDividendService->getAutoDividendSetting($params["market"], $params["setting_for"]);
        return $this->sendResponse($getAutoDividendSetting);
    }

    public function updateAutoDividendSetting(Request $request)
    {
        $setting = $request->all();
        return $this->autoDividendService->updateAutoDividendSetting($setting);
    }

    public function resetAutoDividendSetting(Request $request)
    {
        $setting = $request->all();
        return $this->autoDividendService->resetAutoDividendSetting($setting);
    }

    public function getAllAirdropSetting(): JsonResponse
    {
        try {
            $getAllAirdropSetting = $this->airdropService->getAllAirdropSetting();
            return $this->sendResponse($getAllAirdropSetting);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function enableOrDisableAll(Request $request): JsonResponse
    {
        $params = $request->all();
        $market = $params['market'];
        $settingFor = $params['setting_for'];
        $enable = $params['enable'];

        $update = AutoDividendSetting::where('market', $market)
            ->where('setting_for', $settingFor)
            ->update([
                'enable' => $enable
            ]);
        return $this->sendResponse($update);
    }

    public function enableOrDisableSetting(Request $request): JsonResponse
    {
        $params = $request->all();
        $market = $params['market'];
        $coin = $params['coin'];
        $enable = $params['enable'];

        $query = AutoDividendSetting::where('market', $market);
        if ($coin) {
            $query->where('coin', $coin);
        }
        $result = $query->update([
            'enable' => $enable
        ]);
        return $this->sendResponse($result);
    }

    public function updateAirdropSetting(Request $request): JsonResponse
    {
        $params = $request->all();
        $rules = [
            'currency' => [
                'required',
                Rule::in(Consts::COINS_ALLOW_AIRDROP)
            ],
            'period' => 'required|integer|min:0',
            'unlock_percent' => 'required|numeric|min:0|max:100',
            'payout_amount_btc' => 'required|numeric|min:0',
            'payout_amount_eth' => 'required|numeric|min:0',
            'payout_amount_amal' => 'required|numeric|min:0',
            'payout_time' => 'required|date_format:H:i',
            'min_hold_amal' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($params, $rules);
        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }
        DB::beginTransaction();
        try {
            $updateAirdropSetting = $this->airdropService->updateAirdropSetting($params);
            DB::commit();
            return $this->sendResponse($updateAirdropSetting);
        } catch (HttpException $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getListAirdropUserSetting(Request $request): JsonResponse
    {
        try {
            $getListAirdropUserSetting = $this->airdropService->getListAirdropUserSetting($request->all());
            return $this->sendResponse($getListAirdropUserSetting);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function createAirdropUserSetting(Request $request): JsonResponse
    {
        $params = $request->all();
        $validator = Validator::make($params, [
            'period' => 'required|integer|min:0',
            'unlock_percent' => 'required|numeric|min:0|max:100',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        DB::beginTransaction();

        try {
            $createAirdropUserSetting = $this->airdropService->createAirdropUserSetting($params);
            DB::commit();
            return $this->sendResponse($createAirdropUserSetting);
        } catch (HttpException $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function deleteAirdropUserSetting(Request $request, $userId)
    {
        DB::beginTransaction();
        try {
            $deleteAirdropUserSetting = $this->airdropService->deleteAirdropUserSetting($userId);
            DB::commit();
            return $this->sendResponse($deleteAirdropUserSetting, 'Success.');
        } catch (HttpException $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function updateAirdropUserSetting(Request $request, $userId): JsonResponse
    {
        $params = $request->all();
        $validator = Validator::make($params, [
            'email' => 'required|email|unique:airdrop_user_settings,email,'.$userId.',user_id',
            'period' => 'required|integer',
            'unlock_percent' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }
        DB::beginTransaction();
        try {
            $updateAirdropUserSetting = $this->airdropService->updateAirdropUserSetting($userId, $params);
            DB::commit();
            return $this->sendResponse($updateAirdropUserSetting);
        } catch (HttpException $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getListAirdropHistory(Request $request): JsonResponse
    {
        try {
            $getListAirdropHistory = $this->airdropService->getListAirdropHistory($request->all());
            return $this->sendResponse($getListAirdropHistory);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getAirdropPaymentHistory(Request $request): JsonResponse
    {
        try {
            $getAirdropPaymentHistory = $this->airdropService->getAirdropPaymentHistory($request->all());
            return $this->sendResponse($getAirdropPaymentHistory);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getCashbackHistory(Request $request): JsonResponse
    {
        try {
            $getCashbackHistory = $this->airdropService->getCashbackHistory($request->all());
            return $this->sendResponse($getCashbackHistory);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getTotalBonusDividend(Request $request): JsonResponse
    {
        try {
            $getTotalBonusDividend = $this->airdropService->getTotalBonusDividend($request->all());
            return $this->sendResponse($getTotalBonusDividend);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function resetMaxBonus(Request $request): JsonResponse
    {
        try {
            $resetMaxBonus = $this->airdropService->resetMaxBonus($request->all());
            return $this->sendResponse($resetMaxBonus);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getTotalAMAL(Request $request)
    {
        $setting = $this->airdropService->getAirdropSetting();
        if (!$setting) {
            return config('airdrop.total_amal');
        }
        return $setting->total_supply;
    }

    public function getPairs(Request $request)
    {
        $market = $request->all();
        return $this->autoDividendService->getPairs($market[0]);
    }

    /**
     * @param Request $request
     *
     * List all user's rank by total trading volume according datetime range
     */
    public function getTradingVolumeRanking(Request $request): JsonResponse
    {
        try {
            $listTradingVolume = $this->airdropService->getListTradingVolume($request->all());
            return $this->sendResponse($listTradingVolume);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @param Request $request
     *
     * Apply bonus coins to balance from list users
     */
    public function applyBonusBalance(DividendManualBonusRequest $request): JsonResponse
    {
        $params = $request->all();
        $healthcheck = new HealthCheckService(Consts::HEALTH_CHECK_SERVICE_MANUAL_DIVIDEND, Consts::HEALTH_CHECK_DOMAIN_SPOT);
        try {
            $healthcheck->startLog();
            $listBonus = $this->airdropService->applyBonusBalance($params);
            $healthcheck->endLog();
            return $this->sendResponse($listBonus);
        } catch (\Exception $exception) {
            Log::error($exception);
            $healthcheck->endLog();
            return $this->sendError($exception->getMessage());
        }
    }

    /**
     * @param Request $request
     *
     * Apply bonus coins to balance from list users
     */
    public function refundBonusBalance(Request $request): JsonResponse
    {
        $params = $request->all();
        try {
            $listRefund = $this->airdropService->refundBonusBalance($params);
            return $this->sendResponse($listRefund);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }


    /**
     * @param DividendManualHistoryRequest $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Get dividend manual history records
     */
    public function getManualDividendHistory(Request $request): JsonResponse
    {
        $params = $request->all();
        try {
            $listBonus = $this->airdropService->getDividendManualHistory($params);
            return $this->sendResponse($listBonus);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }


    /**
     * @param DividendAutoHistoryRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAutoDividendHistory(Request $request): JsonResponse
    {
        $params = $request->all();
        try {
            $listBonus = $this->airdropService->getDividendAutoHistory($params);
            return $this->sendResponse($listBonus);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }
}
