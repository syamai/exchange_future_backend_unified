<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Services\ColdWalletSettingService;
use App\Http\Requests\ColdWalletSettingRequest;
use App\Models\Settings;
use Illuminate\Support\Facades\Mail;
use App\Mail\ChangeColdWalletSetting;

class ColdWalletSettingController extends AppBaseController
{
    private ColdWalletSettingService $coldWalletSettingService;

    public function __construct(ColdWalletSettingService $coldWalletSettingService)
    {
        $this->coldWalletSettingService = $coldWalletSettingService;
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->coldWalletSettingService->getColdWalletSettingV2();
        return $this->sendResponse($data);
    }

    public function update(ColdWalletSettingRequest $request): JsonResponse
    {
        try {
            $coldWalletSetting = $request->input('cold_wallet', []);
            $email = $request->input('cold_wallet_holder_email', null);
//            $name = $request->input('cold_wallet_holder_name', null);
//            $mobileNo = $request->input('cold_wallet_holder_mobile_no', null);

            $data = $this->coldWalletSettingService->updateColdWalletSetting($coldWalletSetting, $email);
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function validateAddress(Request $request): JsonResponse
    {
        $data = $this->coldWalletSettingService->validateColdWalletAddress($request->all());
        return $this->sendResponse($data);
    }

    public function validateAddressFromExternal(Request $request): JsonResponse
    {
        $data = $this->coldWalletSettingService->validateColdWalletAddressFromExternal($request->all());
        return $this->sendResponse($data);
    }

    public function commonValidateAddress(Request $request): JsonResponse
    {
        $data = $this->coldWalletSettingService->commonValidateAddress($request->all());
        return $this->sendResponse($data);
    }

    public function sendEmailUpdateColdWallet(Request $request): JsonResponse
    {
        try {
            $email = Settings::select('value')->where('key', 'cold_wallet_holder_email')->pluck('value')->first();
            if ($email) {
                Mail::queue(new ChangeColdWalletSetting($email, $request->input('changedAddress'), $request->input('changedEmail')));
                return $this->sendResponse('', __('common.update.success'));
            }
            return $this->sendResponse('', __('exception.not_found'));
        } catch (\Exception $e) {
            return $this->sendError('Send emails error:' .$e->getMessage());
        }
    }
}
