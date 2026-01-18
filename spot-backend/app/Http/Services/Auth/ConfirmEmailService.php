<?php
/**
 * Created by PhpStorm.
 * Date: 8/16/19
 * Time: 2:28 PM
 */

namespace App\Http\Services\Auth;

use App\Consts;
use App\Jobs\CreateUserAccounts;
use App\Jobs\SendDataToFutureEvent;
use App\Jobs\SendDataToServiceEvent;
use App\Jobs\SendDataToServiceGame;
use App\Jobs\SendNotifyTelegram;
use App\Models\EmailFilter;
use App\Models\UserSecuritySetting;
use App\Models\UserSetting;
use App\Notifications\RegisterCompletedNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ConfirmEmailService
{
    public function confirm($code, $ip, $locale = Consts::DEFAULT_USER_LOCALE)
    {
        $setting = UserSecuritySetting::on('master')
            ->where('email_verification_code', $code)
            ->where('email_verified', 0)
            ->first();

        if ($setting) {
            $this->updateSetting($setting);
            $user = $this->updateUser($setting->id);
            $this->updateLanguageUser($setting->id, $locale);
            $this->checkDevice($setting->id, $ip);
            if ($user->status == Consts::USER_ACTIVE) {
				$this->queueAndNotifyConfirm($user, $setting->id);
			}
        }
    }

    private function updateLanguageUser($userId, $locale)
    {
        UserSetting::updateOrCreate(
            ['user_id' => $userId, 'key' => 'locale'],
            ['value' => $locale]
        );
    }

    private function checkDevice($settingId, $ip)
    {
        $deviceService = new DeviceService();
        $deviceService->checkLastIp($settingId, $ip);
    }

    public function queueAndNotifyConfirm($user, $settingId)
    {
        CreateUserAccounts::dispatch($settingId)->onQueue(Consts::QUEUE_BLOCKCHAIN);
        // Send email register completed
        $user->notify(new RegisterCompletedNotification($user));
		SendNotifyTelegram::dispatch('register', 'Register success: '.$user->email);
		SendDataToServiceEvent::dispatch('register', $user->id);
        SendDataToFutureEvent::dispatch('register', $user->id);
        SendDataToServiceGame::dispatch('register', $user->id);
    }

    private function updateSetting($setting)
    {
        $setting->email_verified = 1;
        $setting->email_verification_code = null;
        $setting->mail_register_created_at = null;
        $setting->save();
    }

    private function updateUser($id)
    {
        $user = User::on('master')
            ->where('id', $id)
            ->first();

        $status = Consts::USER_WARNING;
        //check email whitelist
		$domain = trim(strtolower(explode('@', $user->email)[1] ?? ''));
		$isWhiteList = EmailFilter::where('domain', $domain)
			->where('type', Consts::TYPE_WHITELIST)
			->exists();
		if ($isWhiteList) {
			$status = Consts::USER_ACTIVE;
		}

        $data = ['status' => $status, 'registered_at' => Carbon::now()];
        $user->update($data);

        return $user;
    }
}
