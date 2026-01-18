<?php

namespace App\Http\Services;

use App\Consts;
use Hinaloe\LineNotify\Message\LineMessage;
use KS\Line\LineNotify;
use App\Models\UserSetting;
use App\Events\UserNotificationUpdated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class LineNotifyService
{
    private $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function getAccessToken($response, $goal)
    {
        $state = $response['state'];
        if ($this->checkUserId($state)) {
            $user_id = substr($state, 0, strpos($state, "&"));
            $authcode = $response['code'];
            $channel = 'line';
            $client_id = config('notification.line.client_id');
            $client_secret = config('notification.line.client_secret');
            if ($goal == "web") {
                $redirect_uri = config('app.api_url') . '/api/v1/get-auth-code';
            } else {
                $redirect_uri = config('app.api_url') . '/api/v1/get-auth-code-for-mobile';
            }
            $url = 'https://notify-bot.line.me/oauth/token';
            $data = [
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirect_uri,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'code' => $authcode,
            ];
            $client = new \GuzzleHttp\Client();
            $res = $client->request('POST', $url, [
                'query' => $data
            ]);
            $content = json_decode($res->getBody()->getContents(), true);
            $authkey = $content["access_token"];
            $this->userService->setUserNotificationSetting($user_id, $channel, $authkey, true);
            $userNotificationSetting = $this->userService->getUserNotificationSetting($user_id, Consts::LINE_CHANNEL);
            $usersetting = UserSetting::updateOrCreate(
                ['user_id' => $user_id, 'key' => 'line_notification'],
                ['value' => '1']
            );
            event(new UserNotificationUpdated($user_id, $userNotificationSetting));
            return $content;
        } else {
            $result = [
                "status" => "400",
                "message" => "User ID is invalid",
                "access_token" => null
            ];
            return $result;
        }
    }

    public function checkUserId($state)
    {
        $pos_1 = strpos($state, "&");
        $pos_2 = strpos($state, "$");
        $id = substr($state, 0, $pos_1);
        $id_encrypt_sha1 = strtolower(substr($state, $pos_1 + 1, $pos_2 - $pos_1 - 1));
        if ($id && $id_encrypt_sha1) {
            $random_key = Cache::get("randomKeyForUser$id");
            $id = sha1($random_key + $id);
            return $id == $id_encrypt_sha1;
        }

        return false;
    }

    public function getCallBackUri($response)
    {
        $state = $response['state'];
        $pos_1 = strpos($state, "$");
        $length = strlen($state);
        $callback = substr($state, $pos_1 + 1, $length - $pos_1);

        return $callback;
    }

    public function sendNotification($message, $user_id)
    {
        $channel = "line";
        $is_enable = $this->userService->getUserNotificationSetting($user_id, $channel)->is_enable;
        $token = $this->userService->getUserNotificationSetting($user_id, $channel)->auth_key;
        if ($is_enable == '1') {
            $ln = new LineNotify($token);
            $result = $ln->send($message);
        };
    }

    public function encryptId($request)
    {
        $userId = auth('api')->id();
        $randomKey = rand(10, 1000);
        if (!Cache::has("randomKeyForUser$userId")) {
            $expiresAt = Carbon::now()->addMinutes(Consts::TIME_RANDOM_KEY_ENCRYPT_LINE_LIVING);
            Cache::put("randomKeyForUser$userId", $randomKey, $expiresAt);
        }
        $userId = auth('api')->id();
        $idEncrypt = Cache::get("randomKeyForUser$userId") + $userId;
        return $idEncrypt;
    }
}
