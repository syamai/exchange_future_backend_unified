<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\MasterdataService;
use App\Http\Services\SMSService;
use App\Http\Services\UserService;
use App\Jobs\SendDataToServiceGame;
use App\Models\PhoneOtp;
use App\Models\UserSecuritySetting;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class SMSVerificationController extends AppBaseController
{
    protected $smsService;
    private UserService $userService;

    public function __construct(SMSService $smsService, UserService $userService)
    {
        $this->smsService = $smsService;
        $this->userService = $userService;
    }

    public function getMobileCode()
    {
        /*$data = DB::table('countries')->whereNotNull('calling_code')
            ->selectRaw('country_code as code, name as name_en, calling_code as mobile_code')
            ->get();
        return $this->sendResponse($data);*/
        $countries = MasterdataService::getOneTable('countries');
        $data = [];
        foreach ($countries as $country) {
            if ($country->calling_code) {
                $data[] = [
                    'code' => $country->country_code,
                    'name_en' => $country->name,
                    'mobile_code' => $country->calling_code
                ];
            }
        }
        return $this->sendResponse($data);
    }

    public function sendOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile_code' => 'required|string|exists:countries,country_code',
            'phone' => 'required|string|unique_phone',

        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        // create otp code
        $otpCode = rand(100000, 999999);

        $mobileCode = $request->mobile_code ?? '';
        $phoneNumber = $request->phone ?? '';
        $phone = Utils::getPhone($phoneNumber, $mobileCode);
        $debug = $request->debug ?? false;


        // send OTP to SMS
        try {
            $this->smsService->sendOTP($phone, $otpCode);

            PhoneOtp::updateOrCreate(
                ['phone' => $phone],
                ['otp_code' => $otpCode, 'mobile_code' => $mobileCode, 'phone_number' => $phoneNumber, 'expires_at' => Carbon::now()->addMinutes(30)]
            );
        } catch (\Exception $ex) {
            if ($debug) {
                return $this->sendError($ex->getMessage(), 400);
            }
            return $this->sendError( __('exception.sms_not_send'), 400);
        }

        return $this->sendResponse([], "success");
    }

    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile_code' => 'required|string|exists:countries,country_code',
            'phone' => 'required|string|unique_phone|exists_phone_otp',
            'otp_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $mobileCode = $request->mobile_code ?? '';
        $phoneNumber = $request->phone ?? '';
        $phone = Utils::getPhone($phoneNumber, $mobileCode);

        $otpRecord = PhoneOtp::where('phone', $phone)->first();

        if (!$otpRecord || $otpRecord->otp_code !== $request->otp_code) {
            return $this->sendError("exception.phone_otp_not_correct", 400);
        }

        if ($otpRecord->isExpired()) {
            return $this->sendError("exception.phone_otp_expired", 400);
        }

        //$otpRecord->delete();
        return $this->sendResponse([], "success");
    }

    public function confirmPhoneOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile_code' => 'required|string|exists:countries,country_code',
            'phone' => 'required|string|unique_phone|exists_phone_otp',
            'otp_code' => 'required|string',
        ]);

        $user = $request->user();
        if (!$user) {
            return $this->sendError("exception.not_get_user", 403);
        }

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $phoneNumber = $request->phone ?? '';
        $mobileCode = $request->mobile_code ?? '';
        $phone = Utils::getPhone($phoneNumber, $mobileCode);

        $otpRecord = PhoneOtp::where('phone', $phone)->first();

        if (!$otpRecord || $otpRecord->otp_code !== $request->otp_code) {
            return $this->sendError("exception.phone_otp_not_correct", 400);
        }

        if ($otpRecord->isExpired()) {
            return $this->sendError("exception.phone_otp_expired", 400);
        }

		$userSecuritySettings = UserSecuritySetting::find($user->id);
		if (!$userSecuritySettings) {
			return $this->sendError('exception.phone_not_check_verified');
		}

		if ($userSecuritySettings->phone_verified) {
			return $this->sendError('exception.phone_verified');
		}

        try {

            DB::transaction(function () use ($user, $mobileCode, $phoneNumber, $phone) {
                $user->mobile_code = $mobileCode;
                $user->phone_number = $phoneNumber;
                $user->phone_no = $phone;
                $user->save();
                UserSecuritySetting::where('id', $user->id)->update(['phone_verified' => 1]);
                $this->userService->updateUserSecurityLevel($user->id);
            }, 3);
			SendDataToServiceGame::dispatch('phone', $user->id);

            return $this->sendResponse('', __('Success!'));
        } catch (\Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }
}
