<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Events\OtpUpdated;
use App\Events\UserNotificationUpdated;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\AddBiometricsRequest;
use App\Http\Requests\ChangeAntiPhishingRequest;
use App\Http\Requests\ChangeMypagePasswordRequest;
use App\Http\Requests\ChangeWhiteListSetting;
use App\Http\Requests\CheckEnableDepositRequest;
use App\Http\Requests\CheckEnableWithdrawalRequest;
use App\Http\Requests\ConvertSmallBalanceRequest;
use App\Http\Requests\DelRecoveryCodeWithAuthRequest;
use App\Http\Requests\DisableOtpAuthenticationRequest;
use App\Http\Requests\GenerateQRcodeLoginRequest;
use App\Http\Requests\KYCRequest;
use App\Http\Requests\TransferBalanceRequest;
use App\Http\Requests\UserUpdateOrCreateWithdrawalAddressAPIRequest;
use App\Http\Requests\VerifyBankAccountRequest;
use App\Http\Services\Auth\DeviceService;
use App\Http\Services\EnableTradingSettingService;
use App\Http\Services\SumsubKYCService;
use App\Http\Services\UpdateUserService;
use App\Http\Services\UserService;
use App\Http\Services\UserSettingService;
use App\Jobs\SendDataToServiceGame;
use App\Jobs\SendFavoriteSymbols;
use App\Jobs\SendNotifyTelegram;
use App\Mail\MailVerifyAntiPhishing;
use App\Models\KYC;
use App\Models\SumsubKYC;
use App\Models\User;
use App\Models\UserFavorite;
use App\Models\UserSecuritySetting;
use App\Models\UserSetting;
use App\Models\UserWithdrawalAddress;
use App\Models\WithdrawalLimit;
use App\Notifications\ChangePassword;
use App\Notifications\ReceivedVerifyDocumentNotification;
use App\Utils;
use App\Utils\BigNumber;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\UrlParam;
use Laravel\Passport\Token;
use phpDocumentor\Reflection\Types\True_;
use PHPGangsta_GoogleAuthenticator;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class UserAPIController
 * @package App\Http\Controllers\API
 */
class UserAPIController extends AppBaseController
{
    private UserService $userService;
    private PHPGangsta_GoogleAuthenticator $googleAuthenticator;
    private UserSettingService $userSettingService;
    private UpdateUserService $updateUserService;
    private SumsubKYCService $sumsubKYCService;
    private $redis;

    public function __construct(UpdateUserService $updateUserService)
    {
        $this->userService = new UserService();
        $this->googleAuthenticator = new PHPGangsta_GoogleAuthenticator();
        $this->userSettingService = new UserSettingService();
        $this->updateUserService = $updateUserService;
        $this->sumsubKYCService = new SumsubKYCService();
        $this->redis = Redis::connection(Consts::RC_ORDER_PROCESSOR);
    }

    /**
     * Get the current user
     * @group Account
     *
     * @authenticated
     *
     * @response {
     *  "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *  "data": {
     *    "id": 1
     *  },
     *  "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *  "message": null,
     *  "success": true
     * }
     * @response 500 {
     *  "success": false,
     *  "message": "Server Error",
     *  "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *  "data": null
     * }
     *
     * @response 401 {
     *  "message": "Unauthenticated."
     * }
     *
     */
    #[QueryParam('immediately', 'string', 'immediately', required: false)]

    /**
     * @OA\Get(
     *     path="/api/v1/user",
     *     summary="[Private] Account information (USER_DATA)",
     *     description="Account information (USER_DATA)",
     *     tags={"Account"},
     *     @OA\Parameter ( in="query",
     *         name="immediately",
     *         @OA\Schema (type="string", example="1")
     *     ),
     *     @OA\Response(
     *           response=200,
     *           description="Successful",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example="true"),
     *               @OA\Property(property="message", type="string", example="Jessica Jones"),
     *               @OA\Property(
     *                  property="dataVersion",
     *                  type="string",
     *                  example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"
     *               ),
     *               @OA\Property(
     *                   property="data",
     *                   type="object",
     *                       @OA\Property(property="id", type="int", example="1"),
     *                       @OA\Property(property="name", type="string", example="Bot 1"),
     *                       @OA\Property(property="email", type="email", example="bot1@gmail.com"),
     *                       @OA\Property(property="security_level", type="int", example="3"),
     *                       @OA\Property(property="restrict_mode", type="int", example="0"),
     *                       @OA\Property(property="max_security_level", type="int", example="1"),
     *                       @OA\Property(property="status", type="string", example="active"),
     *                       @OA\Property(property="is_tester", type="string", example="active"),
     *                       @OA\Property(property="hp", type="string", example="123..."),
     *                       @OA\Property(property="bank", type="string", example="000...."),
     *                       @OA\Property(property="real_account_no", type="string", example="123..."),
     *                       @OA\Property(property="virtual_account_no", type="string", example="123..."),
     *                       @OA\Property(property="account_note", type="string", example="xxxx.."),
     *                       @OA\Property(property="referrer_id", type="int", example="2"),
     *                       @OA\Property(property="referrer_code", type="string", example="khxdpQ"),
     *                       @OA\Property(property="type", type="string", example="normal"),
     *                       @OA\Property(property="phone_no", type="string", example="123..."),
     *                       @OA\Property(property="memo", type="string", example="Bot 1"),
     *                       @OA\Property(property="created_at", type="TIMESTAMP", example="2024-05-14T01:49:11.000000Z"),
     *                       @OA\Property(property="updated_at", type="TIMESTAMP", example="2024-05-14T01:49:11.000000Z"),
     *                       @OA\Property(property="fake_name", type="string", example="Bot 1"),
     *                       @OA\Property(property="is_anti_phishing", type="int", example="0"),
     *                       @OA\Property(property="anti_phishing_code", type="string", example="Bot 1"),
     *                       @OA\Property(property="uid", type="string", example="Bot 1"),
     *                       @OA\Property(property="fingerprint", type="string", example="MIICIjANB...."),
     *                       @OA\Property(property="faceID", type="string", example="Bot 1"),
     *                       @OA\Property(property="pnl_chart_code", type="string", example="66e351ffe0c688..."),
     *                       @OA\Property(property="user_fee_level", type="object", example= {"id": 1,"user_id":1,"active_time":null, "fee_level":null, "created_at":"2024-05-20T04:51:40.000000Z", "updated_at":"2024-05-20T04:51:40.000000Z"}),
     *                       @OA\Property(property="security_setting", type="object", example={"id": 1,"email_verified": 1,"mail_register_created_at": null,"phone_verified": 0,"identity_verified": 0,"bank_account_verified": 0,"otp_verified": 0,"use_fake_name": 1,"created_at": "2024-05-20T04:51:40.000000Z","updated_at": "2024-05-20T04:51:40.000000Z"}),
     *                       @OA\Property(property="user_anti_phishing_active_latest", type="object", example="[]")
     *              )
     *          )
     *       ),
     *       @OA\Response(
     *          response=500,
     *          description="Server error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Server Error"),
     *              @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *              @OA\Property(property="data", type="string", example=null)
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated.")
     *          )
     *      ),
     *     security={{ "apiAuth": {} }}
     * )
     */
    public function getCurrentUser(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        if ($request->input('immediately')) {
            $user = User::on('master')->find($userId);
            $token = Token::where(['user_id' => $user->id, 'type' => 0])->first();
            $user->pnl_chart_code = $this->encodePnlCode($token->id);//dd($request);
            return $this->sendResponse($user->load([
                'userFeeLevel',
                'securitySetting',
                'userAntiPhishingActiveLatest' => function ($query) {
                    $query->first();
                }
            ]));
        } else {
            $user = User::on('master')->find($userId)->load([
                'userFeeLevel',
                'securitySetting',
                'userAntiPhishingActiveLatest' => function ($query) {
                    $query->first();
                }
            ]);
            $token = Token::where(['user_id' => $user->id, 'type' => 0])->first();
            $user->pnl_chart_code = $this->encodePnlCode($token->id);
            return $this->sendResponse($user);
        }
    }

    private function encodePnlCode($apiKey): string
    {
        $encrypt = '6fe17230cd48b9a5';
        return strtr($apiKey, '0123456789abcdef', $encrypt);
    }

    /**
     * Get current user balance
     *
     * @group Spot
     *
     * @subgroup Balances
     *
     * @param Request $request
     * @param $store
     * @return JsonResponse
     *
     * @response {
     * "success": true,
     * "message": null,
     * "dataVersion": "2b4cfde274aa7aa6b37074759abfdcf78396047a",
     * "data": {
     * "airdrop": {
     * "btc": {
     * "balance": 0,
     * "available_balance": 0
     * },
     * "bch": {
     * "balance": 0,
     * "available_balance": 0
     * }
     * },
     * "main": {
     * "btc": {
     * "balance": 0,
     * "available_balance": 0,
     * "blockchain_address": null
     * },
     * "bch": {
     * "balance": 0,
     * "available_balance": 0,
     * "blockchain_address": null
     * }
     * },
     * "mam": {
     * "btc": {
     * "balance": 0,
     * "available_balance": 0
     * },
     * "bch": {
     * "balance": 0,
     * "available_balance": 0
     * }
     * },
     * "margin": {
     * "btc": {
     * "balance": 0,
     * "available_balance": 0
     * },
     * "bch": {
     * "balance": 0,
     * "available_balance": 0
     * }
     * },
     * "spot": {
     * "btc": {
     * "balance": 0,
     * "available_balance": 0
     * },
     * "bch": {
     * "balance": 0,
     * "available_balance": 0
     * }
     * }
     * }
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated.",
     * }
     */
    #[UrlParam("store", "string", "Store (margin or spot). ", required: false, example: "margin")]
    public function getCurrentUserBalance(Request $request, $store = null): JsonResponse
    {
        try {
            $user = $request->user();
            $accounts = $this->userService->getUserAccounts($user->id, $store);

            return $this->sendResponse($accounts);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getSecuritySettings(Request $request): JsonResponse
    {
        try {
            $securitySettings = $this->userService->getSecuritySettings($request);

            return $this->sendResponse($securitySettings);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getUserReferralFriends(Request $request): JsonResponse
    {
        $user = $request->user();
        try {
            $userReferralFriends = $this->userService->getUserReferralFriends($user->id, $request->input());
            return $this->sendResponse($userReferralFriends);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }

    public function getAllReferrer(Request $request): JsonResponse
    {
        $user = $request->user();
        try {
            $userReferralFriends = $this->userService->getAllReferrer($user->id, $request->input());
            return $this->sendResponse($userReferralFriends);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }

    public function getUserReferralCommission(Request $request): JsonResponse
    {
        $user = $request->user();
        try {
            $userReferralCommissions = $this->userService->getUserReferralCommission($user->id, $request->input());
            return $this->sendResponse($userReferralCommissions);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }

    public function getTopUserReferralCommission(): JsonResponse
    {
        try {
            $topUserRefCommissions = $this->userService->getTopUserReferralCommission();
            return $this->sendResponse($topUserRefCommissions);
        } catch (Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex);
        }
    }

    public function createUserQrcode(Request $request)
    {
        $userId = $request->user()->id;
        $url = $request->url;
        try {
            $UserQrcode = $this->userService->createUserQrcode($userId, $url);
            return $this->sendResponse($UserQrcode);
        } catch (Exception $e) {
            Log::error($e);
            $this->sendError($e);
        }
    }

    public function getUserNotificationSettings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $userNotificationSetting = $this->userService->getEnableUserNotificationSettings($user->id);

            return $this->sendResponse($userNotificationSetting);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getUserSettings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $securitySettings = DB::table('user_settings')
                ->select('key', 'value')
                ->where('user_id', $user->id)
                ->get();

            return $this->sendResponse($securitySettings);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getUserSamsubKyc(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $userKyc = SumsubKYC::where('user_id', $user->id)->first();

            if ($userKyc) {
//                $userKyc->id_front = Utils::getPresignedUrl($userKyc->id_front);
//                $userKyc->id_back = Utils::getPresignedUrl($userKyc->id_back);
//                $userKyc->id_selfie = Utils::getPresignedUrl($userKyc->id_selfie);

                $workflowRunUrl = "";
                if ($userKyc->id_applicant) {
                    try {
                        $sumsubData = $this->sumsubKYCService->getApplicantsStatus($userKyc->id_applicant);

                        if ($sumsubData && !empty($sumsubData['reviewStatus']) && $sumsubData['reviewStatus'] != $userKyc->bank_status) {
                            $userKyc->bank_status = $sumsubData['reviewStatus'];
                            $userKyc->save();
                        }
                    } catch (Exception $ex) {
                    }

                    if ($userKyc->bank_status == 'init') {
                        $sumbubLink = $this->sumsubKYCService->getWebSDKLink($user->id);
                        if ($sumbubLink && !empty($sumbubLink['url'])) {
                            $workflowRunUrl = $sumbubLink['url'];
                        }
                    }
                }

                $userKyc->workflowRunUrl = $workflowRunUrl;
            }

            return $this->sendResponse($userKyc);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function startUserSamsubKyc(Request $request) {
        try {
            $request->validate([
                'first_name' => 'required|string',
                'last_name' => 'required|string',
            ]);
//            $validator = Validator::make(
//                $request->all(),
//                [
//                    'first_name' => 'required|string',
//                    'last_name' => 'required|string',
//                ]
//            );
//            if ($validator->fails()) {
//                return $this->sendError($validator->messages());
//            }

            $first_name = trim($request->first_name ?? '');
            $last_name = trim($request->last_name ?? '');

            if (empty($first_name) || empty($last_name)) {
                return $this->sendError(__('account.request.name'));
            }

            $user = $request->user();
            $userKyc = SumsubKYC::where('user_id', $user->id)->first();
            if (!$userKyc) {
                $userKyc = SumsubKYC::create(['first_name' => $first_name, 'last_name' => $last_name, 'full_name' => $first_name. " ". $last_name, 'user_id' => $user->id]);
            }

            if (!$userKyc) {
                return $this->sendError(__('account.kyc.not_found'));
            }

            if (!$userKyc->id_applicant) {

                try {
                    $sumsubData = $this->sumsubKYCService->checkUserStatus($user->id);
                    if ($sumsubData) {
                        $userKyc->id_applicant = $sumsubData['id'];
                        $userKyc->save();
                    }
                } catch (Exception $ex) {
                    $sumsubData = $this->sumsubKYCService->createApplicant($user->id, $first_name, $last_name);
                    if ($sumsubData && !empty($sumsubData['id'])) {
                        $userKyc->id_applicant = $sumsubData['id'];
                        $userKyc->save();
                    }
                }

            } else if ($userKyc->bank_status == 'init') {
                /*$sumsubData = $this->sumsubKYCService->checkUserStatus($user->id);
                if ($sumsubData && !empty($sumsubData['review']) && !empty($sumsubData['review']['reviewStatus'])) {
                    if ($sumsubData['review']['reviewStatus'] != $userKyc->bank_status) {
                        $userKyc->bank_status = $sumsubData['review']['reviewStatus'];
                        $userKyc->save();
                    }
                }*/
            }

            if (!$userKyc->id_applicant) {
                return $this->sendError(__('account.kyc.not_exists'));
            }

            if ($userKyc->bank_status == 'init') {
                $userKyc->update(['first_name' => $first_name, 'last_name' => $last_name, 'full_name' => $first_name. " ". $last_name]);
                // update info sumsub
                $this->sumsubKYCService->changeApplicantsInfo($userKyc->id_applicant, ['firstName' => $first_name,'lastName' => $last_name]);
            }

            if ($userKyc->status == 'verified') {
                return $this->sendError(__('KYC approved'));
            }

            if ($userKyc->status == 'rejected' || ($userKyc->bank_status && $userKyc->bank_status != 'init')) {
                // change status onHold applicants sumsub
                if ($userKyc->bank_status == 'pending') {
                    $this->sumsubKYCService->onHoldReviewApplicantsProfile($userKyc->id_applicant);
                }

                // reset applicants sumsub
                $result = $this->sumsubKYCService->resetApplicantsProfile($userKyc->id_applicant);
                if (!empty($result['ok'])) {
                    $userKyc->update([
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'full_name' => $first_name. " ". $last_name,
                        'id_front' => null,
                        'id_back' => null,
                        'id_selfie' => null,
                        'gender' => null,
                        'country' => null,
                        'id_number' => null,
                        'bank_status' => 'init',
                        'status' => 'pending'
                    ]);
                }
            }

            if ($userKyc->bank_status && $userKyc->bank_status != 'init') {
                return $this->sendError(__("KYC is in review"));
            }

            // get url review
            $sumbubLink = $this->sumsubKYCService->getWebSDKLink($user->id);
            if ($sumbubLink && !empty($sumbubLink['url'])) {
                return $this->sendResponse(array('url' => $sumbubLink['url']));
            }

            return $this->sendError(__('Have errors during setup. Please register again'));



        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function webhookSamsubKyc(Request $request) {
        $clientRequest = $request->ip();
        $log = date('Y-m-d H:i:s'). ' id:' .$clientRequest.' - ' .json_encode($request->all());
        $path = "webhookSamsubKyc_log_".date("Ymd").".txt";
        Storage::disk('public')->append($path, $log);

        try {
            $reviewStatus = $request->reviewStatus ?? '';
            $applicantId = $request->applicantId ?? '';
            $externalUserId = $request->externalUserId ?? '';
            $typeMess = $request->type ?? '';
            $reviewResult = $request->reviewResult ?? null;
            $kycInfo = SumsubKYC::where('id_applicant', $applicantId)
                ->first();
            if (!$kycInfo) {
                throw new Exception("id_applicant not exists ({$applicantId})");
            }
            $user = $kycInfo->user;

            $countries = $this->sumsubKYCService->getcountries();
            $genders = ['M' => 'Male', 'F' => 'Female'];
            if ($user) {
                $dataUpdate = [
                	'review_result' => null
				];
                if (in_array($reviewStatus, ['pending', 'completed'])) {
                	if ($reviewStatus == 'completed') {
                		if (isset($reviewResult['reviewAnswer'])) {
                			if (strtoupper($reviewResult['reviewAnswer']) == 'RED') {
								$reviewStatus = 'rejected';
								$dataUpdate['review_result'] = isset($reviewResult['rejectLabels']) ? $reviewResult['rejectLabels'] : null;
							}
						}
					}
                    if ($externalUserId == $this->sumsubKYCService->getExternalUserId($user->id)) {
                        //get info user
                        //$applicantId = "66a07e250d591b6318a3f667";
                        $sumsubData = $this->sumsubKYCService->getApplicantsData($applicantId);
                        $sumsubDataInfo = isset($sumsubData['info']) && isset($sumsubData['info']['idDocs']) && isset($sumsubData['info']['idDocs'][0]) ? $sumsubData['info']['idDocs'][0] : null;
                        if ($sumsubDataInfo) {
                            //$firstName = $sumsubDataInfo
                            $country = isset($sumsubDataInfo['country']) ? (isset($countries[$sumsubDataInfo['country']]) ? $countries[$sumsubDataInfo['country']] : $sumsubDataInfo['country']) : $kycInfo->country;
                            if ($country != $kycInfo->country) {
                                $dataUpdate['country'] = $country;
                            }

                            $gender = isset($sumsubDataInfo['gender']) ? (isset($genders[$sumsubDataInfo['gender']]) ? $genders[$sumsubDataInfo['gender']] : $sumsubDataInfo['gender']) : $kycInfo->gender;
                            if ($gender != $kycInfo->gender) {
                                $dataUpdate['gender'] = $gender;
                            }

                            $idNumber = isset($sumsubDataInfo['additionalNumber']) ? $sumsubDataInfo['additionalNumber'] : (isset($sumsubDataInfo['number']) ? $sumsubDataInfo['number'] : $kycInfo->id_number);
                            if ($idNumber != $kycInfo->id_number) {
                                $dataUpdate['id_number'] = $idNumber;
                            }

                            /*$firstName = isset($sumsubDataInfo['firstName']) ? $sumsubDataInfo['firstName'] : $kycInfo->first_name;
                            $lastName = isset($sumsubDataInfo['lastName']) ? $sumsubDataInfo['lastName'] : $kycInfo->last_name;
                            $fullName = $firstName . ' ' . $lastName;

                            if ($firstName != $kycInfo->first_name || $lastName != $kycInfo->last_name) {
                                $dataUpdate['first_name'] = $firstName;
                                $dataUpdate['last_name'] = $lastName;
                                $dataUpdate['full_name'] = $fullName;
                            }*/
                        }

                        // get image docs
                        $infoDocs = $this->sumsubKYCService->getDocumentImage($kycInfo);
                        $dataUpdate = array_merge($dataUpdate, $infoDocs);
                        //dd($sumsubDataInfo);

                    } else {
                        throw new Exception("external user id not same ({$externalUserId})");
                    }
                }

                if ($reviewStatus != $kycInfo->bank_status) {
                    $dataUpdate['bank_status'] = $reviewStatus;
                    if (in_array($reviewStatus, ['pending']) && $typeMess == 'applicantPending') {
                    	//send notify telegram
						SendNotifyTelegram::dispatch('kyc', 'KYC check: '.$user->email);
						$dataUpdate['send_notify_telegram'] = 1;
					}
                }

                if ($dataUpdate) {
                    $kycInfo->update($dataUpdate);
                    $log = date('Y-m-d H:i:s'). ' - success: id '.$kycInfo->id.' - '.json_encode($dataUpdate);
                    $path = "webhookSamsubKyc_log_success_".date("Ymd").".txt";
                    Storage::disk('public')->append($path, $log);
                }
            } else {
                throw new Exception("not get user kyc ({$applicantId})");
            }
        } catch (Exception $exception) {
            $log = date('Y-m-d H:i:s'). ' - error:'.$exception->getMessage().' - '.json_encode($request->all());
            $path = "webhookSamsubKyc_log_error_".date("Ymd").".txt";
            Storage::disk('public')->append($path, $log);
        }

    }

    public function changeWhiteListSetting(ChangeWhiteListSetting $request)
    {
        $active = $request->active;
        try {
            UserSetting::updateOrCreate(
                ['user_id' => $request->user()->id, 'key' => 'whitelist'],
                ['value' => $active]
            );
        } catch (Exception $e) {
            Log::error($e);
            $this->sendError($e);
        }

        return $this->sendResponse(array('whitelist' => $active));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/balance/{currency}",
     *     summary="[Private] Get Detail User Balance",
     *     description="Get balances of user by currency",
     *     tags={"Account"},
     *     @OA\Parameter(
     *         description="Currency name",
     *         in="path",
     *         name="currency",
     *         @OA\Schema(
     *             type="string",
     *             example="usd"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Store name (main,mam,margin,spot,...)",
     *         in="query",
     *         name="coin",
     *         @OA\Schema(
     *             type="string",
     *             example="main"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Get balances success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example="null"),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                example={
     *                    "balance": "1000.0000000000",
     *                    "available_balance": "999.0000000000",
     *                    "usd_amount": "0.0000000000",
     *                    "blockchain_address": "0x3929F5083Afd20588443e2F3051473Ca552606F6"
     *                }
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response="419",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "success": false,
     *                 "message": "Server Error",
     *                 "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *                 "data": null
     *             }
     *         )
     *     ),
     *     security={{ "apiAuth": {} }}
     * )
     */

    /**
     * Get details user balance
     *
     * @group Spot
     *
     * @subgroup Balances
     *
     * @param Request $request
     * @param $currency
     * @return JsonResponse
     *
     * @response {
     * "success": true,
     * "message": null,
     * "dataVersion": "2b4cfde274aa7aa6b37074759abfdcf78396047a",
     * "data": {
     * "balance": "1000.0000000000",
     * "available_balance": "999.0000000000",
     * "usd_amount": "0.0000000000",
     * "blockchain_address": "0x3929F5083Afd20588443e2F3051473Ca552606F6"
     * }
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated."
     * }
     */
    #[UrlParam("currency", "string", "Currency Balance. ", required: true, example: 'usdt')]
    #[QueryParam("store", "string", "Balance type (spot, margin, ...). ", required: true, example: 'spot')]
    public function getDetailsUserBalance(Request $request, $currency): JsonResponse
    {
        try {
            $user = $request->user();
            $balance = $this->userService->getDetailsUserBalance($user->id, $currency, false, $request->store);

            return $this->sendResponse($balance);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getDetailsUserUsdBalance(Request $request, $currency): JsonResponse
    {
        try {
            $user = $request->user();
            $balance = $this->userService->getDetailsUserUsdBalance($user->id, $currency, false, $request->store);

            return $this->sendResponse($balance);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getDetailsUserSpotBalance(Request $request, $currency): JsonResponse
    {
        try {
            $user = $request->user();
            $balance = $this->userService->getDetailsUserSpotBalance($user->id, $currency, false);

            return $this->sendResponse($balance);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getOrderBookSettings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currency = $request->currency;

            $coin = $request->coin;
            $settings = $this->userService->getOrderBookSettings($user->id, $currency, $coin);
            return $this->sendResponse($settings);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function updateOrderBookSettings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currency = $request->currency;
            $coin = $request->coin;

            $settings = $this->userService->createOrUpdateOrderBookSettings($user->id, $currency, $coin,
                $request->all());
            return $this->sendResponse($settings);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getFavorite(Request $request): JsonResponse
    {
        try {
            return $this->sendResponse($request->user()->favorites);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function insertFavorite(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $favorite = UserFavorite::firstOrCreate([
                'user_id' => $request->user()->id,
                'coin_pair' => $request->coin_pair
            ]);
            SendFavoriteSymbols::dispatchIfNeed($userId);
            return $this->sendResponse($favorite);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function deleteFavorite(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $result = UserFavorite::where('user_id', $request->user()->id)
                ->where('id', $request->id)
                ->delete();
            SendFavoriteSymbols::dispatchIfNeed($userId);
            return $this->sendResponse(array('result' => $result));
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function reorderFavorites(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $data = $request->input('symbols');
        $symbols = explode(',', $data);
        DB::beginTransaction();
        try {
            UserFavorite::where('user_id', $userId)->delete();
            foreach ($symbols as $symbol) {
                if ($symbol) {
                    UserFavorite::firstOrCreate([
                        'user_id' => $userId,
                        'coin_pair' => $symbol
                    ]);
                }
            }
            DB::commit();
            SendFavoriteSymbols::dispatchIfNeed($userId);
            return $this->sendResponse([]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function updateRestrictMode(Request $request): JsonResponse
    {
        try {
            $result = User::where('id', Auth::id())
                ->update(['restrict_mode' => $request->input('is_restrict_mode')]);

            return $this->sendResponse(array('result' => $result));
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function deleteDevice($id): JsonResponse
    {
        $userId = auth('api')->id();

        try {
            $result = $this->userService->deleteDevice($userId, $id);
            return $this->sendResponse(array('result' => $result));
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function grantDevicePermission($code): JsonResponse
    {
        try {
            $deviceService = new DeviceService();
            $result = $deviceService->authorizeDevice($code);

            return $this->sendResponse(array('result' => $result));
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function verifyAntiPhishing($code): JsonResponse
    {
        try {
            $userService = new UserService();
            $result = $userService->decodeAntiPhishing($code);

            return $this->sendResponse(array('result' => $result));
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function resendConfirmEmailAntiPhishing(Request $request)
    {
        $email = $request->email;
        $phishingCode = $request->anti_phishing_code;
        $type = $request->type;
        if ($email) {
            $user = User::where('email', $email)->firstOrFail();
            $userService = new UserService();
            $code = $userService->genCodeAntiPhising($user, $phishingCode);
            Mail::queue(new MailVerifyAntiPhishing($user, $code, strtolower($type)));
            return $this->sendResponse([]);
        }
    }

    public function getDeviceRegister(): JsonResponse
    {
        $userId = Auth::id();

        try {
            $data = $this->userService->getDeviceRegister($userId);
            return $this->sendResponse($data);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getUserDevice(): JsonResponse
    {
        try {
            $devices = $this->userService->getUserDevices(Auth::id());

            return $this->sendResponse($devices);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getConnections(): JsonResponse
    {
        try {
            $connections = $this->userService->getUserAccessHistories(Auth::id());

            return $this->sendResponse($connections);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getKeyGoogleAuthen(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if ($user->google_authentication) {
                $secret = $user->google_authentication;
            } else {
                $secret = $this->googleAuthenticator->createSecret();
                $this->userService->storeSecret($secret, $user->id);
            }
            $qrCodeUrl = $this->googleAuthenticator->getQRCodeGoogleUrl($user->email, $secret, env('APP_NAME'));
            return $this->sendResponse(array('key' => $secret, 'url' => $qrCodeUrl));
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function addSecuritySettingOtp(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|correct_password',
            'code' => 'required|numeric|digits:6|otp_not_used|correct_otp',
        ]);
        try {
            $userId = Auth::id();

            DB::transaction(function () use ($userId) {
                UserSecuritySetting::where('id', $userId)->update(['otp_verified' => 1]);
                $this->userService->updateUserSecurityLevel($userId);
            }, 3);

            event(new OtpUpdated($userId));

			SendDataToServiceGame::dispatch('kyc', $userId);

            return $this->sendResponse('', __('Success!'));
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function verifyCode(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if ($user->google_authentication) {
                if ($user->verifyOtp($request->input('authentication_code'))) {
                    return $this->sendResponse('', 'Success!');
                } else {
                    return $this->sendError('Wrong 2FA Code!');
                }
            }
            return $this->sendError('Please, Setup GG authen!');
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getWithdrawalAddress(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = UserWithdrawalAddress::with(
                ['network' => function ($q) {
                    $q->select(['id', 'symbol', 'network_code', 'name', 'deposit_confirmation', 'network_withdraw_enable', 'network_deposit_enable']);
                }
                ])
                ->where('user_id', $user->id);
            $coin = $request->input('coin');
            $networkId = $request->input('network_id');

            $isWhiteList = $user->userSetting()->where('key', 'whitelist')->value('value');
            $query->when(intval($isWhiteList) === 1, function ($q) {
                $q->where('is_whitelist', true);
            })->when(!empty($coin), function ($q) use ($coin) {
                $q->where('coin', $coin);
            })->when(!empty($networkId), function ($q) use ($networkId) {
                $q->where('network_id', $networkId);
            });

            $data = $query->orderBy('id', 'desc')->get();

            return $this->sendResponse($data);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getNetworkWithdrawalAddress(CheckEnableWithdrawalRequest $request): JsonResponse
    {
        $currency = $request->input('currency');

        try {
            $result = $this->userService->getWithdrawalNetworks($currency);

            return $this->sendResponse($result);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getWithdrawalsAddress(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', Consts::DEFAULT_PER_PAGE);
            $user = $request->user();
            $params = $request->all();
            $data = UserWithdrawalAddress::with(
                ['network' => function ($q) {
                    $q->select(['id', 'symbol', 'network_code', 'name', 'deposit_confirmation', 'network_withdraw_enable', 'network_deposit_enable']);
                }])
                ->where('user_id', $user->id)
                ->when(array_key_exists('isWhiteList', $params), function ($query) use ($params) {
                    if ($params['isWhiteList']) {
                        return $query->where('is_whitelist', true);
                    } else {
                        return $query->where('is_whitelist', false);
                    }
                })
                ->orderBy('id', 'desc')
                ->paginate($limit);
            return $this->sendResponse($data);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getWithdrawalLimitBTC(): JsonResponse
    {
        try {
            $data = WithdrawalLimit::where('currency', 'btc')->get();

            return $this->sendResponse($data);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function updateOrCreateWithdrawalAddress(UserUpdateOrCreateWithdrawalAddressAPIRequest $request
    ): JsonResponse {
        //TODO
        $coin = $request->input('coin');
        $address = [
            'wallet_name' => $request->input('wallet_name'),
            'tag' => $request->input('tag') ?? null,
        ];
        try {
            $userWithdrawalAddress = UserWithdrawalAddress::where('coin', $coin)
                ->updateOrCreate([
                    'user_id' => $request->user()->id,
                    'coin' => $coin,
                    'wallet_address' => $request->input('wallet_address')
                ], $address);
            return $this->sendResponse($userWithdrawalAddress);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function createDepositAddress(CheckEnableDepositRequest $request): JsonResponse
    {
        $currency = $request->input('currency');

        try {
            $result = $this->userService->createDepositAddress($currency);
            return $this->sendResponse($result);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/deposit-address",
     *     summary="[Private] Get Deposit Address",
     *     description="Get deposit address",
     *     tags={"Account"},
     *     @OA\Parameter(
     *         description="Currency name",
     *         in="query",
     *         name="currency",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             example="btc"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Get data success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example="null"),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="fcf932880bcb19ea4b0d3b1bae533d5e2e5ae244"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                example={
     *                    "blockchain_address": "mnh1vbecZR3nTHesZz46dMCHMP4FVJJtk8",
     *                    "qrcode": "/storage/qr_codes/mnh1vbecZR3nTHesZz46dMCHMP4FVJJtk8.png"
     *                }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response="419",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "success": false,
     *                 "message": "Server Error",
     *                 "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *                 "data": null
     *             }
     *         )
     *     ),
     *     security={{ "apiAuth": {} }}
     * )
     */
    public function getDepositAddress(CheckEnableDepositRequest $request): JsonResponse
    {
        $currency = $request->input('currency');
        $networkId = $request->input('network_id');
        if (empty($networkId)) {
            return $this->sendError('Network not received');
        }

        //check user verify phone
        $user = $request->user();
        if (!$user) {
            return $this->sendError('exception.user_get_error');
        }

        $userSecuritySettings = UserSecuritySetting::find($user->id);
        if (!$userSecuritySettings || !$userSecuritySettings->phone_verified) {
            return $this->sendError('exception.phone_not_verified');
        }

        try {
            $result = $this->userService->createAddressQr($currency, $networkId);

            return $this->sendResponse($result);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getNetworkAddress(CheckEnableDepositRequest $request): JsonResponse
    {
        $currency = $request->input('currency');

        try {

            $result = $this->userService->getAddressNetworks($currency);

            return $this->sendResponse($result);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function changePassword(ChangeMypagePasswordRequest $request): JsonResponse
    {
        $user = Auth::user();
        try {
            $accessToken = explode(' ', $request->header('Authorization'))[1] ?? null;
            //$user->password = Utils::encrypt($request->input('new_password'));
            $user->password = bcrypt($request->input('new_password'));
            $user->fingerprint = null;
            $user->faceID = null;
            $user->save();
            $user->revokeAllToken();
            if ($accessToken) {
                Utils::revokeTokensInFuture($accessToken);
            }

            //send email change password
			$user->notify(new ChangePassword());
            return $this->sendResponse($user);
        } catch (\Exception $e) {
            logger($e);
            return $this->sendError($e);
        }
    }

    public function delGoogleAuth(Request $request): JsonResponse
    {
        logger('delGoogleAuth');
        logger($request->all());
        logger('delGoogleAuth end');
        $request->validate([
            'password' => 'required|correct_password',
            'code' => $request->user()->securitySetting->otp_verified ? 'required|numeric|digits:6|otp_not_used|correct_otp' : ''
        ]);

        $user = Auth::user();
        return $this->stopUsingOtp($user);
    }

    public function updateOrCreateUserLocale(Request $request): JsonResponse
    {
        $locale = $request->input('lang', Consts::DEFAULT_USER_LOCALE);
        return $this->sendResponse(['locale' => $this->userService->updateOrCreateUserLocale($locale)]);
    }

    public function setLocale(Request $request): JsonResponse
    {
        return $this->sendResponse(Utils::setLocale($request));
    }

    public function users(Request $request): JsonResponse
    {
        $data = $this->userService->getUsersForAdmin($request->all());
        return $this->sendResponse($data);
    }

    public function getTotalUser(Request $request): JsonResponse
    {
        $data = $this->userService->getTotalUser();
        return $this->sendResponse($data);
    }

    public function referrers(Request $request): JsonResponse
    {
        $data = $this->userService->getReferrers($request->all());
        return $this->sendResponse($data);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $user = $this->updateUserService->update($request);
            return $this->sendResponse($user);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getPhoneVerificationData(): JsonResponse
    {
        $reqdate = date('YmdHis');

        $postcpid = 'gndplus';
        $posturlCode = '01005';
        $postclntReqNum = $reqdate . rand(100000, 999999);
        $postreqdate = $reqdate;
        $postretUrl = url('/verify-number');

        $reqInfo = $posturlCode . '/' . $postclntReqNum . '/' . $postreqdate;

        $oED = new Crypt();
        $certPath = storage_path('/gndplusCert.der');
        $sEncryptedData = $oED->encrypt($reqInfo, $certPath);

        if ($sEncryptedData == false) {
            echo $oED->ErrorCode;
        }
        $data = [
            'sEncryptedData' => $sEncryptedData,
            'postretUrl' => $postretUrl,
            'postcpid' => $postcpid,
        ];

        Cache::put('phone_verification_request_' . Auth::id(), $postclntReqNum, 60);

        return $this->sendResponse($data);
    }

    public function getReferrerFee(Request $request): JsonResponse
    {
        $data = $this->userService->getReferrerFee($request->all());
        return $this->sendResponse($data);
    }

    public function verifyBankAccount(VerifyBankAccountRequest $request): JsonResponse
    {
        try {
            $bankName = $request->bank_name;
            $accountName = $request->account_name;
            $accountNumber = $request->account_number;

            $user = $request->user();
            if ($accountName != $user->name) {
                throw new HttpException(422, __('exception.account_mismatch'));
            }

            if ($this->isBankAccountInUse($bankName, $accountNumber)) {
                throw new HttpException(422, __('exception.bank_account_in_use'));
            }

            $result = $this->queryBankAccountInfo($request);
            $json = json_decode($result);
            if ($json->RSLT_CD != '000') {
                logger("Verify bank account failed: $result");
                throw new HttpException(422, __('exception.invalid_account'));
            }

            $responseAccountName = $json->RESP_DATA[0]->ACCT_NM;
            if ($accountName != $responseAccountName) {
                logger("Verify bank account failed: $result");
                throw new HttpException(422, __('exception.invalid_account'));
            }

            $this->updateBankAccount($bankName, $accountNumber);
            return response()->json(['success' => 'true']);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    private function isBankAccountInUse($bankName, $accountNumber)
    {
        return DB::table('users')
            ->where('real_account_no', $accountNumber)
            ->where('bank', $bankName)
            ->first();
    }

    private function updateBankAccount($bankName, $accountNumber)
    {
        $userId = Auth::id();
        DB::beginTransaction();
        try {
            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'security_level' => Consts::SECURITY_LEVEL_BANK,
                    'real_account_no' => $accountNumber,
                    'bank' => $bankName
                ]);
            DB::table('user_security_settings')
                ->where('id', $userId)
                ->update(['bank_account_verified' => 1]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
        }
    }

    private function queryBankAccountInfo($request): array|bool|string|null
    {
        $url = 'https://gw.coocon.co.kr/sol/gateway/acctnm_rcms_wapi.jsp';

        $transactionNo = Utils::currentMilliseconds() / 100 % 1000000;
        $data = [
            'SECR_KEY' => 'NApdW1mXE5K6PSC7G8N7',
            'KEY' => 'ACCTNM_RCMS_WAPI',
            'REQ_DATA' => [
                [
                    'BANK_CD' => $request->bank_id,
                    'SEARCH_ACCT_NO' => $request->account_number,
                    'ACNM_NO' => $request->date_of_birth,
                    'ICHE_AMT' => '',
                    'TRSC_SEQ_NO' => "0$transactionNo"
                ]
            ]
        ];

        $body = [
            'JSONData' => json_encode($data)
        ];
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $url, [
            'query' => $body
        ]);
        $content = $res->getBody()->getContents();
        // $content = '{"RESP_DATA":[{"ACCT_NM":"a","TRSC_SEQ_NO":"0000001"}],"RSLT_MSG":"정상처리","RSLT_CD":"000"}';
        return mb_convert_encoding($content, 'utf-8', 'euc-kr');
    }

    public function disableOtpAuthentication(DisableOtpAuthenticationRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();
        return $this->stopUsingOtp($user);
    }

    public function delRecoveryCodeWithAuth(DelRecoveryCodeWithAuthRequest $request)
    {
        $user = Auth::user();
        return $this->stopUsingOtp($user);
    }

    private function stopUsingOtp($user): JsonResponse
    {
        DB::beginTransaction();
		$securitySetting = UserSecuritySetting::where('id', $user->id)->first();
        $securityLevel = $user->security_level > Consts::SECURITY_LEVEL_OTP ? $user->security_level : (!$securitySetting->identity_verified ? Consts::SECURITY_LEVEL_EMAIL : Consts::SECURITY_LEVEL_IDENTITY);
        try {
            User::where('id', $user->id)
                ->update([
                    'google_authentication' => null,
                    'security_level' => $securityLevel
                ]);
            UserSecuritySetting::where('id', $user->id)
                ->update(['otp_verified' => 0]);

            //$this->userService->updateUserSecurityLevel($user->id);
			SendDataToServiceGame::dispatch('kyc', $user->id);

            event(new OtpUpdated($user->id));
            DB::commit();
            return $this->sendResponse('', __('Stop using OTP success!'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e);
        }
    }

    public function createIdentity(KYCRequest $request): JsonResponse
    {
        $full_name = $request->full_name;
        $country = $request->country;
        $gender = $request->gender;
        $id_number = $request->id_number;

        if (!empty($request->otp)) {
            $otpCache = Cache::get('otp_verify_' . Auth::id());
            if ($otpCache != $request->otp) {
                return $this->sendError(__('auth.otp_code_invalid'));
            } else {
                Cache::forget('otp_verify_' . Auth::id());
            }
        } else {
            return $this->sendError(__('auth.otp_code_required'));
        }

        $files = $this->processFiles($request);

        $columns = [
            'full_name' => $full_name,
            'country' => $country,
            'gender' => $gender,
            'id_number' => $id_number,
        ];
        try {
            $message = __('account.identity.success');
            $existed = $request->has('id') ? KYC::where('id', $request->id)->exists() : false;

            if ($existed) {
                $kyc = KYC::where('user_id', Auth::id())->orderBy('id', 'desc')->first();
                if ($kyc->status == Consts::KYC_STATUS_PENDING) {
                    $message = __('account.identity.errors.status_pendding');
                    return $this->sendError($message);
                } else {
                    $params = array_merge($columns, $files);
                    KYC::where('id', $request->id)->update(array_merge($params, [
                        'status' => Consts::KYC_STATUS_PENDING
                    ]));
                    $message = __('account.identity.update_success');
                }
            } else {
                $params = array_merge($columns, $files, ['user_id' => Auth::id()]);
                KYC::create($params);
            }
            $data = KYC::where('user_id', Auth::id())->orderBy('id', 'desc')->first();
            $user = request()->user();
            //Mail::queue(new ReceivedVerifyDocument(request()->user()));
            $user->notify(new ReceivedVerifyDocumentNotification($user->status));

            return $this->sendResponse($data, $message);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function otpVerify(Request $request): JsonResponse
    {
        $request->validate([
            'otp' => 'required|numeric|digits:6|otp_not_used|correct_otp'
        ]);
        Cache::put('otp_verify_' . Auth::id(), $request->otp, 60 * 60);
        return $this->sendResponse($request->otp);
    }

    private function processFiles(Request $request): array
    {
        $columns = array();
        if ($request->has('id_front')) {
            $columns['id_front'] = $this->saveImageToKYCStorage($request->id_front, $request->country, 'id_front');
        }
        if ($request->has('id_back')) {
            $columns['id_back'] = $this->saveImageToKYCStorage($request->id_back, $request->country, 'id_back');
        }
        if ($request->has('id_selfie')) {
            $columns['id_selfie'] = $this->saveImageToKYCStorage($request->id_selfie, $request->country, 'id_selfie');
        }
        return $columns;
    }

    public function saveImageToKYCStorage($fileImage, $country, $key): string
    {
        $pathFolder = "kyc/$country";
        return Utils::saveFileToStorage($fileImage, $pathFolder, $key);
    }

    // public function updateBankAccountStatus(Request $request)
    // {
    //     $user = Auth::user();
    //     try {
    //         KYC::where('user_id', $user->id)->where('bank_status', [Consts::BANK_STATUS_UNVERIFIED, Consts::BANK_STATUS_REJECTED])
    //             ->update([
    //                 'bank_status' => $request->input('status'),
    //             ]);
    //         return $this->sendResponse('', __('update_success'));
    //     } catch (\Exception $e) {
    //         Log::error($e);
    //         return $this->sendError($e);
    //     }
    // }

    public function updateSettingOtp(Request $request, $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = User::find($id);
            $userSecuritySetting = UserSecuritySetting::find($id);
            if (empty($userSecuritySetting) || empty($user)) {
                return $this->sendError('Not found');
            }

            UserSecuritySetting::where('id', $id)
                ->update([
                    'otp_verified' => $request->otp_verified
                ]);

			$securityLevel = $user->security_level > Consts::SECURITY_LEVEL_OTP ? $user->security_level : (!$userSecuritySetting->identity_verified ? Consts::SECURITY_LEVEL_EMAIL : Consts::SECURITY_LEVEL_IDENTITY);
            User::where('id', $id)
                ->update([
                    'google_authentication' => null,
                    'security_level' => $securityLevel
                ]);

            DB::commit();
            return $this->sendResponse($userSecuritySetting);
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function convertSmallBalance(ConvertSmallBalanceRequest $request): JsonResponse
    {
        $coins = explode(',', trim($request->coins));
        DB::beginTransaction();
        try {
            $result = $this->userService->convertSmallBalance($coins);
            DB::commit();
            return $this->sendResponse($result, 'ok');
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e);
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/transfer-balance",
     *     summary="[Private] Transfer Balance",
     *     description="Transfer balance from a store to another store",
     *     tags={"Account"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="coin_value", description="The amount", type="decimal", example=1),
     *              @OA\Property(property="coin_type", description="The coin name", type="string", example="btc"),
     *              @OA\Property(
     *                  property="from",
     *                  description="The type balance",
     *                  type="string",
     *                  example="main"
     *              ),
     *              @OA\Property(
     *                  property="to",
     *                  description="The type balance",
     *                  type="string",
     *                  example="margin"
     *              ),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example="ok"),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="fcf932880bcb19ea4b0d3b1bae533d5e2e5ae244"
     *             ),
     *             @OA\Property(property="data", type="boolean", example="true"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "success": false,
     *                 "message": "Server Error",
     *                 "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *                 "data": null
     *             }
     *         )
     *     ),
     *     security={{ "apiAuth": {} }}
     * )
     */
    public function transferBalance(TransferBalanceRequest $request): JsonResponse
    {
        Config::set('database.default', Consts::DB_CONNECTION_MASTER);
        $coinValue = $request->coin_value;
        $coinType = $request->coin_type;
        $fromBalance = $request->from;
        $toBalance = $request->to;
        $userId = request()->user()->id;

        if ($coinType != Consts::CURRENCY_BTC && $coinType != Consts::CURRENCY_AMAL) {
            if ($fromBalance == Consts::TYPE_MARGIN_BALANCE || $toBalance == Consts::TYPE_MARGIN_BALANCE) {
                return $this->sendResponse(false, 'Can\'t transfer to margin.');
            }
        }

        if ($fromBalance == Consts::TYPE_MAIN_BALANCE && $toBalance == Consts::TYPE_AIRDROP_BALANCE) {
            DB::beginTransaction();
            try {
                $result = $this->userService->transferBalanceFromMainToAirdrop($coinValue, $coinType, $fromBalance,
                    $toBalance, $userId);
                DB::commit();
                return $this->sendResponse($result, $result ? 'ok' : 'fail');
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error($e);
                return $this->sendError($e->getMessage());
            }
        }

        $isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
        if ($isSpotMainBalance) {
            if ($fromBalance == Consts::TYPE_MAIN_BALANCE || $toBalance == Consts::TYPE_MAIN_BALANCE) {
                return $this->sendResponse(false, 'Not support.');
            }
        }

        if ($fromBalance == Consts::TYPE_AIRDROP_BALANCE) {
            if (!in_array($coinType, Consts::AIRDROP_TABLES)) {
                return $this->sendResponse(false, 'Not support.');
            }
        }

        DB::beginTransaction();
        try {
            $transfer = $this->userService->transferBalance($coinValue, $coinType, $fromBalance, $toBalance, $userId);
            DB::commit();
            $result = false;
            if ($transfer) {
                $result = true;
                if ($toBalance == Consts::TYPE_EXCHANGE_BALANCE) {
                    $transactionService = app(\App\Http\Services\TransactionService::class);
                    $transactionService->sendMETransferSpot($transfer, $userId, $coinType, BigNumber::new($coinValue)->toString(), true);
                } else if ($fromBalance == Consts::TYPE_EXCHANGE_BALANCE) {
                    $transactionService = app(\App\Http\Services\TransactionService::class);
                    $transactionService->sendMETransferSpot($transfer, $userId, $coinType, BigNumber::new($coinValue)->toString(), false);
                }
            }


            return $this->sendResponse($result, $result ? 'ok' : 'fail');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function changeEmailNotification(Request $request): JsonResponse
    {
        $active = $request->active;
        $userId = $request->user()->id;
        $typeNotification = 'email_notification';

        try {
            $this->userSettingService->updateCreateUserSetting($typeNotification, $active, $userId);
            if ($active == true) {
                $this->userService->enableUserNotificationSetting($userId, Consts::MAIL_CHANNEL);
            } else {
                $this->userService->disableUserNotificationSetting($userId, Consts::MAIL_CHANNEL);
            }
            $userNotificationSetting = $this->userService->getUserNotificationSetting($userId, Consts::MAIL_CHANNEL);
            event(new UserNotificationUpdated($userId, $userNotificationSetting));
        } catch (Exception $e) {
            Log::error($e);
            $this->sendError($e);
        }

        return $this->sendResponse(array($typeNotification => $active));
    }

    public function changeTelegramNotification(Request $request): JsonResponse
    {
        $active = $request->active;
        $typeNotification = 'telegram_notification';

        if ($active == 0) {
            $userId = $request->user()->id;

            try {
                $this->updateUserService->changeTelegramNotify($typeNotification, $active, $userId);
            } catch (Exception $e) {
                Log::error($e);

                return $this->sendError($e->getMessage());
            }
        }

        return $this->sendResponse(array($typeNotification => $active));
    }

    public function changeLineNotification(Request $request): JsonResponse
    {
        $user_id = auth('api')->id();
        try {
            $active = $request->active;
            UserSetting::updateOrCreate(
                ['user_id' => $user_id, 'key' => 'line_notification'],
                ['value' => $active]
            );
            if (!$request->active) {
                $this->userService->disableUserNotificationSetting($user_id, 'line');
            }
            return $this->sendResponse(array('line_notification' => $active, 'user_id' => $user_id));
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }

    public function changeAmlPay(Request $request): JsonResponse
    {
        $active = $request->active;
        $userId = $request->user()->id;
        $typeNotification = 'amal_pay';
        $typeWalletDefault = 'amal_pay_wallet';

        try {
            $this->userSettingService->updateCreateUserSetting($typeNotification, $active, $userId);
            if (!$this->userSettingService->checkUserSetting($typeWalletDefault, $userId)) {
                $this->userSettingService->updateCreateUserSetting($typeWalletDefault, Consts::TYPE_MAIN_BALANCE,
                    $userId);
            }
        } catch (Exception $e) {
            Log::error($e);
            $this->sendError($e);
        }

        return $this->sendResponse(array($typeNotification => $active));
    }

    public function changeWalletAmalFee(Request $request): JsonResponse
    {
        $active = $request->wallet_name;
        $userId = $request->user()->id;
        if (!$this->userSettingService->getValueFromKey('amal_pay', $userId)) {
            return $this->sendError(__('circuit_breaker_setting.update_fail'));
        }
        $typeWalletDefault = 'amal_pay_wallet';
        try {
            $this->userSettingService->updateCreateUserSetting($typeWalletDefault, $active, $userId);
        } catch (Exception $e) {
            Log::error($e);
            $this->sendError($e);
        }
        return $this->sendResponse(array($typeWalletDefault => $active));
    }

    public function changeAnimationStatus(Request $request): JsonResponse
    {
        $active = $request->status;
        $user_id = $request->user()->id;
        try {
            $changeAnimationStatus = $this->userSettingService->updateCreateUserSetting('animation', $active, $user_id);
        } catch (Exception $e) {
            Log::error($e);
            $this->sendError($e);
        }
        return $this->sendResponse($changeAnimationStatus);
    }

    public function getAnimationStatus(Request $request): JsonResponse
    {
        $user_id = $request->user()->id;
        try {
            $getAnimationStatus = $this->userSettingService->getValueFromKey('animation', $user_id);
        } catch (Exception $e) {
            Log::error($e);
            $this->sendError($e);
        }
        return $this->sendResponse($getAnimationStatus);
    }

    public function getCustomerInformation(Request $request): JsonResponse
    {
        $user = $this->userService->getCustomerInformation($request);
        return $this->sendResponse($user);
    }

    public function getUserPairTradingSetting(Request $request): JsonResponse
    {
        $inputs = $request->all();
        $validator = Validator::make($inputs, [
            'coin' => 'required|string',
            'currency' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }
        $inputs['email'] = User::find($request->user()->id)->email;
        $enableTradingSettingService = new EnableTradingSettingService();
        $data = $enableTradingSettingService->getUserPairTradingSetting($inputs);
        return $this->sendResponse($data);
    }

    public function updateUserFakeName(Request $request): JsonResponse
    {
        try {
            return $this->sendResponse($this->userService->updateFakeName($request->get('fake_name')));
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/user/anti-phishing",
     *     summary="[Private] Change anti phishing",
     *     description="Change anti phishing",
     *     tags={"Private API"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="is_anti_phishing", description="Disable or enable anti phishing", type="boolean", example=0),
     *              @OA\Property(property="anti_phishing_code", description="Code of anti phishing", type="string", example="123abc"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Change success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example="success"),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="fcf932880bcb19ea4b0d3b1bae533d5e2e5ae244"
     *             ),
     *             @OA\Property(property="data", type="boolean", example="true"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "success": false,
     *                 "message": "Server Error",
     *                 "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *                 "data": null
     *             }
     *         )
     *     ),
     *     security={{ "apiAuth": {} }}
     * )
     */
    public function changeAntiPhishing(ChangeAntiPhishingRequest $request)
    {
        try {
            return $this->sendResponse($this->userService->updateAntiPhishing($request->only([
                'is_anti_phishing',
                'anti_phishing_code'
            ])));
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function saveStatusKyc(Request $request)
    {
        try {
            if (!$request->refId) {
                abort(404, "RefId not found");
            }
            DB::beginTransaction();
            $user = User::findOrFail((int)$request->refId);
            if (!$user) {
                abort(404, "RefId not found");
            }
            $kyc = KYC::where('user_id', $user->id)->first();
            $statusRequest = Consts::KYC_STATUS_PENDING;
            $headers = [
                'Authorization' => env('KYC_AUTH_API_KEY'),
                'cache-control' => 'no-cache'
            ];
            $url = 'https://kyc.blockpass.org/kyc/1.0/connect/' . env('KYC_CLIENT_ID') . '/refId/' . $user->id;
            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->get($url)
                ->object()
                ->data;
            if ($response && $response->status) {
                if ($response->status == 'approved') {
                    $statusRequest = Consts::KYC_STATUS_VERIFIED;
                }
                if ($response->status == 'rejected') {
                    $statusRequest = Consts::KYC_STATUS_REJECTED;
                }
            }
            if (!$kyc && $response) {
                $data = [
                    'full_name' => $response->identities->family_name->value . ' ' . $response->identities->given_name->value,
                    'id_front' => '',
                    'id_back' => '',
                    'id_selfie' => '',
                    'gender' => '',
                    'country' => '',
                    'id_number' => '',
                    'user_id' => $user->id,
                    'status' => $statusRequest,
                ];
                KYC::create($data);
            }
            if ($kyc && $response) {
                if ($kyc->status != $statusRequest) {
                    $kyc->status = $statusRequest;
                    $kyc->save();
                }
            }
            if ($statusRequest == Consts::KYC_STATUS_VERIFIED) {
                UserSecuritySetting::where('id', $user->id)->update(['identity_verified' => 1]);
                $this->userService->updateUserSecurityLevel($user->id);
            }
            DB::commit();
            return true;
        } catch (HttpException $e) {
            Log::error($e);
            DB::rollBack();
            return $this->sendError($e->getMessage());
        }
    }

    public function addBiometrics(AddBiometricsRequest $request)
    {

        try {
            return $this->sendResponse($this->userService->addBiometrics($request));
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/qr-code/generate",
     *     summary="[Public] Generate QR code waiting to login from mobile",
     *     description="Generate QR code waiting to login from mobile",
     *     tags={"Public API"},
     *     @OA\Parameter(
     *         description="Generate QR code",
     *         in="query",
     *         name="random",
     *         @OA\Schema(
     *             type="string",
     *             example="000-000-000"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Generate QR code success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example="null"),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                example={
     *                    "random": "000-000-000",
     *                    "qrcode": "000-000-000"
     *                }
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "success": false,
     *                 "message": "Server Error",
     *                 "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *                 "data": null
     *             }
     *         )
     *     ),
     *     security={{ "apiAuth": {} }}
     * )
     */
    public function generateQRcodeLogin(GenerateQRcodeLoginRequest $request): JsonResponse
    {
        AbstractDeviceParser::setVersionTruncation(AbstractDeviceParser::VERSION_TRUNCATION_NONE);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $deviceDetector = new DeviceDetector($userAgent);
        $deviceDetector->parse();
        $platform = @$deviceDetector->getClient()['name'] . " " . @$deviceDetector->getClient()['version'];
        $ip = request()->ip();
        $location = \Location::get(request()->ip());
        $random = $request->random;
        $qrcode = Utils::makeQrCodeLogin($random, $ip, $location, $platform);
        return $this->sendResponse($qrcode);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/qr-code/check",
     *     summary="[Public] API check exist or expired of QR code",
     *     description="API check exist or expired of QR code",
     *     tags={"Public API"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="random", description="Random string from generate QR code api", type="string", example="000-000-000"),
     *              @OA\Property(property="qrcode", description="Random string from generate QR code api", type="string", example="000-000-000"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Change success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example="success"),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                example={
     *                    "random": "000-000-000",
     *                    "qrcode": "000-000-000",
     *                    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIyIiwianRpIjoiMWM4ZjMzOTBhODI2M2QxMjZkZTBhN2YyMWEwNjljOGQwYTUxMWYxOTE2MjgzZDdiNjQ4OWUzZDBmOTFlMmU4ZDhlZjk4NWI0YzFhYzY2YTMiLCJpYXQiOjE2NzgxODMwMzQuNjUxMjI4LCJuYmYiOjE2NzgxODMwMzQuNjUxMjMsImV4cCI6MTcwOTgwNTQzNC42MjEwNiwic3ViIjoiMTEiLCJzY29wZXMiOltdfQ.xLB-2UcENJkatdHO4TE6muZb_TRMCRVmcpAiI-wjPatQ5o-bzSfJ-if9hVJ575K58Wpqm-iC9a_5PVuFKXQ9sDQycUgdxWB-6frpjaHX6rwxkTipcbUP2VD-p4D4BKcZD3V-WEusQKNrFXSdk7-FAsFvU7WqWYbjMsXcQWu02lr8-CIkIDa65AuqF6K8gS5ueJaea9yjtFdBgbX4qEjyY2Ji4D5RT8mkWbTBLn_Prjnstw0jcjLyLUFJFAFZM_2febaEwOG0qWy0-E1gm1ENbi72lXCCfeqhZJxAqX8HsS9OWhTrngunJp0guDBMt10j7Eyo41cREdmz6cOktFW_AMa2noQK9ZjIEPGXqwmLfCR1A7lurodhzfQMw6nSEeisz_quCazZ1p7RHSKMDLi2GpPZAfjJjlvuhvQWqpW5edgkvZaWR2IsMoxT35aIaDXscCJ1PtcoT5PHPmkjoYUtMpnBqcKfesPzhHywL07G251rAuXH0OCBqrtGtS0CTRFE0eCrXfVIFKyEf0ltl9jn4aL5B55HqStltF2JLtx_u9tkB-h6YA-44KJS8-7pR9qGT48tkj19Zj3jW3BB2z4UezHIvzU7REH6IOMuEn9ctApaB-Tle1VzTYYVfPEerRWKml0Itpr2m5jE4Aab56O9SN8FVcyFNaNiqXjgAra6Bjg"
     *                }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "success": false,
     *                 "message": "Server Error",
     *                 "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *                 "data": null
     *             }
     *         )
     *     ),
     *     security={{ "apiAuth": {} }}
     * )
     */
    public function checkQRcodeLogin(Request $request): JsonResponse
    {
        $random = $request->random;
        $qrcode = $request->qrcode;
        $check = $this->userService->checkQRcodeLogin($random, $qrcode);
        $qrcode = $this->redis->get($random);
        if ($check) {
            return $this->sendResponse(json_decode($qrcode));
        }
        return $this->sendResponse(false);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/qr-code/scan",
     *     summary="[Private] API apply login with QR code from call from mobile",
     *     description="API apply login with QR code from call from mobile",
     *     tags={"Private API"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="random", description="Random string from generate QR code api", type="string", example="000-000-000"),
     *              @OA\Property(property="qrcode", description="Random string from generate QR code api", type="string", example="000-000-000"),
     *              @OA\Property(property="status", description="Status of scanning | 0: cancel, 1: scanning, 2: confirm", type="string", example="2"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Change success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example="success"),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="fcf932880bcb19ea4b0d3b1bae533d5e2e5ae244"
     *             ),
     *             @OA\Property(property="data", type="boolean", example="true"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "success": false,
     *                 "message": "Server Error",
     *                 "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *                 "data": null
     *             }
     *         )
     *     ),
     *     security={{ "apiAuth": {} }}
     * )
     */
    public function mobileScanLogin(Request $request): JsonResponse
    {
        $random = $request->random;
        $qrcode = $request->qrcode;
        $status = $request->status;
        $check = $this->userService->checkQRcodeLogin($random, $qrcode);
        if ($check) {
            $accessToken = '';
            if ($status == 2) {
                $user = Auth::user();
                $accessToken = $user->createToken('Token Name')->accessToken;
            }
            return $this->sendResponse($this->userService->mobileScanQRcode($random, $qrcode, $accessToken, $status));
        }
        return $this->sendResponse($check);
    }

    public function updateOrCreateDeviceToken(Request $request): JsonResponse
    {
        $data = $this->userService->updateOrCreateDeviceToken($request->input('device_token', null));

        return $this->sendResponse($data);
    }
}
