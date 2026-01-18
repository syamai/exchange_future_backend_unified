<?php
namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Http\Services\LineNotifyService;
use Illuminate\Support\Facades\Log;
use App\Utils;

class LineChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    private $lineNotifyService;

    public function send($notifiable, Notification $notification)
    {

        $this->lineNotifyService = new LineNotifyService();

        $mes = @$notification->content ?? "";

        $mes = Utils::TransferCustomTemplateForNotification($mes);
        if (property_exists($notification, 'content')) {
            $notification->content = $mes;
        }
        $message = $notification->toLine($notifiable);
        $this->lineNotifyService->sendNotification($message, $notifiable->id);
    }
}
