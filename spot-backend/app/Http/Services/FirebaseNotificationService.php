<?php

namespace App\Http\Services;

use App\Models\UserSetting;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\CloudMessage;

class FirebaseNotificationService
{
    public static function send(int $userId, string $title, string $body, array $data = null): void
    {
        try {
            $messaging = app('firebase.messaging');
            $notification = FirebaseNotificationService::getNotification($title, $body);
            $deviceToken = UserSetting::query()->where([
                ['user_id', $userId],
                ['key', 'device_token']
            ])->value('value') ?? null;
            if ($deviceToken) {
                $message = CloudMessage::withTarget('token', $deviceToken)->withNotification($notification);
                $messaging->send($message);
            }
        }
        catch (\Exception $e) {
            logger()->error("NOTIFY FIREBASE FAIL ==========" . $e->getMessage());
            logger()->info("USER ID: " . json_encode($userId));
        }
    }

    public static function getNotification(string $title, string $body): array
    {
        return [
            'title' => $title,
            'body' => $body
        ];
    }
}
