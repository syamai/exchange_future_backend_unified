<?php

namespace App\Channels;

use App\Http\Services\BotTelegramService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use App\Utils;

class TelegramChannel
{
    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param \Illuminate\Notifications\Notification $notification
     * @return void
     */
    private $botTelegramService;

    public function send($notifiable, Notification $notification)
    {

        $this->botTelegramService = new BotTelegramService();

        $mes = @$notification->content ?? "";

        $mes = Utils::TransferCustomTemplateForNotification($mes);
        if (property_exists($notification, 'content')) {
            $notification->content = $mes;
        }
        $message = $notification->toTelegram($notifiable);

        logger()->info('TelegramChannel: send: '.$message);

        // Send notification to the $notifiable instance...
        $this->botTelegramService->sendMessage($message, $notifiable->id);
    }
}
