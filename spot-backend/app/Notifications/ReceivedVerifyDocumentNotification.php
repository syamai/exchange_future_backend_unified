<?php
/**
 * Created by PhpStorm.
 * User: amanpuri
 * Date: 06/06/2019
 * Time: 15:24
 */

namespace App\Notifications;

use App\Consts;
use App\Http\Services\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ReceivedVerifyDocumentNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    private $status;
    private $userService;

    public function __construct($status)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->status = $status;
        $this->userService = new UserService();
    }

    public function toLine($notifiable)
    {
        return $this->getMessage($notifiable);
    }

    public function toMail($notifiable)
    {
        return new \App\Mail\ReceivedVerifyDocument($notifiable);
    }

    public function toTelegram($notifiable)
    {
        return $this->getMessage($notifiable);
    }

    private function getMessage($notifiable)
    {
        $locale = $this->userService->getUserLocale($notifiable->id);
        $message = env('APP_NAME')
            . "\n" . __('emails.received_verify_document.line_1', [], $locale)
            . "\n" . __('emails.received_verify_document.line_2', [], $locale)
            . "\n" . __('emails.received_verify_document.line_3', [], $locale)
            . "\n" . __('emails.received_verify_document.line_4', [], $locale)
            . "\n" . __('emails.received_verify_document.line_5', [], $locale)
            . "\n" . __('emails.received_verify_document.line_6', [], $locale);

        return $message;
    }
}
