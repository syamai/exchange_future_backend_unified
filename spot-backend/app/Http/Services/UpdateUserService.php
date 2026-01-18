<?php


namespace App\Http\Services;

use App\Consts;
use App\Jobs\CreateUserAccounts;
use App\Jobs\UpdateAffiliateTrees;
use App\Jobs\UpdateReferrerDetail;
use App\Models\User;
use App\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateUserService
{
    private ForgotPasswordService $forgotPasswordService;
    private UserService $userService;
    private UserSettingService $userSettingService;

    /**
     * UpdateUserService constructor.
     * @param ForgotPasswordService $forgotPasswordService
     * @param UserService $userService
     * @param UserSettingService $userSettingService
     */
    public function __construct(
        ForgotPasswordService $forgotPasswordService,
        UserService $userService,
        UserSettingService $userSettingService
    ) {
        $this->forgotPasswordService = $forgotPasswordService;
        $this->userService = $userService;
        $this->userSettingService = $userSettingService;
    }

    /**
     * @param $request
     * @return mixed
     * @throws \Exception
     */
    public function update($request): mixed
    {
        DB::connection('master')->beginTransaction();
        try {
            $user = User::find($request->input('id'));
            $originStatus = $user->status;

            if ($user->status !== $request->input('status')) {
                $user->status = $request->input('status');
                $newPassword = null;
                $token = null;

                if ($user->status === Consts::USER_ACTIVE) {
                    CreateUserAccounts::dispatch($user->id)->onQueue(Consts::QUEUE_BLOCKCHAIN);
                    UpdateReferrerDetail::dispatch($user->id);
                    UpdateAffiliateTrees::dispatch($user->id)->onQueue(Consts::QUEUE_UPDATE_AFFILIATE_TREE);

                    $newPassword = Utils::generateRandomString(8);
                    $user->password = bcrypt($newPassword);

                    $this->forgotPasswordService->deleteByEmail($user->email);
                    $token = $this->forgotPasswordService->createTokenSha256($user->email);
                }

                $this->userService->disableOrEnableUser($user, $newPassword, $token);

                if ($request->input('status') === Consts::USER_ACTIVE) {
                    $this->userService->expiryDateVerification($request->input('id'));
                }
            }
            $user->max_security_level = $request->input('max_security_level');
            $user->hp = $request->input('hp');
            $user->bank = $request->input('bank');
            $user->real_account_no = $request->input('realAccountNo');
            $user->virtual_account_no = $request->input('virtualAccountNo');
            $user->referrer_id = $request->input('referrerId');
            $user->type = $request->input('type');
            $user->security_level = $request->input('security_level');
            $user->memo = $request->input('memo');
            $user->save();

            DB::connection('master')->commit();

            if ($originStatus !== $request->input('status')) {
                CreateUserAccounts::dispatch($user->id)->onQueue(Consts::QUEUE_BLOCKCHAIN);
            }

            $user['referrer_name'] = ($user->referrer_id) ? User::find($user->referrer_id)->name : null;

            return $user;
        } catch (\Exception $e) {
            DB::connection('master')->rollBack();
            throw $e;
        }
    }

    /**
     * Change Telegram Notify
     * @param $typeNotification
     * @param $active
     * @param $userId
     * @throws \Exception
     */
    public function changeTelegramNotify($typeNotification, $active, $userId)
    {
        DB::connection('master')->beginTransaction();

        try {
            $this->userSettingService->updateCreateUserSetting($typeNotification, $active, $userId);
            $this->userService->disableUserNotificationSetting($userId, Consts::TELEGRAM_CHANNEL);
            DB::connection('master')->commit();
        } catch (\Exception $e) {
            DB::connection('master')->rollBack();
            throw $e;
        }
    }
}
