<?php

namespace App\Http\Services;

use App\Consts;
use App\Events\UserNotificationUpdated;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class BotTelegramService
{
    private $userService;
    private $userSettingService;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->userSettingService = new UserSettingService();
    }

    public function sendMessage($message, $userId)
    {
        $chatId = $this->getChatIds($userId);

        if ($chatId != null) {
            $this->sendMessageToAllChannel($chatId, $message, $userId);
        }
    }

    public function getChatIds($userId)
    {
        $driver = $this->userService->getUserNotificationSetting($userId, Consts::TELEGRAM_CHANNEL);
        return ($driver && $driver->is_enable) ? $driver->auth_key : null;
    }

    public function sendMessageToAllChannel($chatId, $message, $userId)
    {
        if (!empty($chatId)) {
            try {
                $telegramToken = config('telegram.token');
                $data = [
                    'chat_id' => $chatId,
                    'text' => $message
                ];
                $telegramUrl = config('telegram.prefix_url') . "/bot$telegramToken/sendMessage";
                $client = new Client();
                $client->request('GET', $telegramUrl, ['query' => $data]);
            } catch (Exception $e) {
                if ($e->getCode() === 401) {
                    $active = 0;
                    $this->updateUserNotificationSetting($active, $userId, $chatId);
                }
            }
        }
    }

    public function setChatIdApi($messageBot)
    {
        $text = '';
        $chat_id = '';

        if (array_key_exists('message', $messageBot)) {
            $chat_id = $messageBot['message']['chat']['id'];
            if (array_key_exists('text', $messageBot['message'])) {
                $text = $messageBot['message']['text'];
            }
        } elseif (array_key_exists('callback_query', $messageBot)) {
            $chat_id = $messageBot['callback_query']['message']['chat']['id'];
            $text = $messageBot['callback_query']['data'];
        }

        if (!empty($text)) {
            $start = strpos($text, '/start');

            if ($start !== false) {
                $user_id = $this->getUserIdOnMessage($start, $text);

                if ($user_id !== '' && is_numeric($user_id)) {
                    $this->saveUserNotification($chat_id, $user_id);
                }
                //$this->sendMessageToAllChannel($chat_id, 'Welcome to this Robot');
            }
        }
    }

    public function getUserIdOnMessage($start, $text)
    {
        $pos = $start + strlen('/start');
        $user_id = substr($text, $pos, strlen($text));

        return $user_id;
    }

    public function saveUserNotification($chat_id, $user_id)
    {
        $active = 1;
        $this->updateUserNotificationSetting($active, $user_id, $chat_id);
    }

    private function updateUserNotificationSetting($active, $user_id, $chat_id)
    {
        $typeNotification = 'telegram_notification';
        $this->userSettingService->updateCreateUserSetting($typeNotification, $active, $user_id);
        $this->userService->setUserNotificationSetting($user_id, Consts::TELEGRAM_CHANNEL, $chat_id, $active);
        $userNotificationSetting = $this->userService->getUserNotificationSetting($user_id, Consts::TELEGRAM_CHANNEL);
        event(new UserNotificationUpdated($user_id, $userNotificationSetting));
    }
}
