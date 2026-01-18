<?php
namespace App\Http\Services;

use App\Models\UserSetting;

class UserSettingService
{
    const USER_REFERRAL_COMMISSION_LIMIT = 3;
    const CACHE_LIVE_TIME = 300; //5  minutes
    public function updateCreateUserSetting($typeNotification, $active, $userId)
    {
        return UserSetting::updateOrCreate(
            ['user_id' => $userId, 'key' => $typeNotification],
            ['value' => $active]
        );
    }
    public function checkUserSetting($typeNotification, $userId)
    {
        $settings = UserSetting::where('user_id', $userId)->where('key', $typeNotification)->first();
        if (!$settings) {
            return false;
        }
        return true;
    }
    public function getValueFromKey($key, $userId)
    {
        $settings = UserSetting::where('user_id', $userId)->where('key', $key)->first();
        if (!$settings) {
            return false;
        }
        return $settings->value;
    }
    public function updateValueByKey($key, $value)
    {
        return UserSetting::where('key', $key)
                    ->update(['value' => $value]);
    }
}
