<?php

namespace App\Notifications;

use App\Consts;
use App\Http\Services\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class BanAccount extends BaseNotification implements ShouldQueue
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

    public function toMail($notifiable)
    {
        return new \App\Mail\BanAccount($notifiable);
    }

    public function toLine($notifiable)
    {
        return $this->getMessage($notifiable);
    }

    private function getMessage($notifiable)
    {
        $contactLink = config('app.zendesk_domain');
        $locale = $this->userService->getUserLocale($notifiable->id);
        $message = "<b>" . ' ' . config('app.name') . ':  ' . "</b>"
            . " \n " . __('emails.ban_account.line_1', [], $locale)
            . " \n " . __('emails.ban_account.line_2', [], $locale)
            . " \n \n" . __('emails.ban_account.line_3', [], $locale)
            . " \n \n" . $contactLink;

        return $message;
    }

    public function toTelegram($notifiable)
    {
        return $this->getMessage($notifiable);
    }
}
