<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Services\UserService;
use App\Mail\MarginDeleverageMail;

class DeleverageAlerts extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected int $userId;
    protected string $symbol;
    protected UserService $userService;

    public function __construct($userId, $symbol)
    {
        $this->userId = $userId;
        $this->symbol = $symbol;
    }

    public function toMail($notifiable): MarginDeleverageMail
    {
        return new MarginDeleverageMail($this->userId, $this->symbol);
    }

    public function toLine($notifiable): string
    {
        return $this->getMessage($notifiable);
    }

    public function toTelegram($notifiable): string
    {
        return $this->getMessage($notifiable);
    }

    private function getMessage($notifiable): string
    {
        $this->userService = new UserService();
        $locale = $this->userService->getUserLocale($this->userId);
        $email = $this->userService->getEmailByUserId($this->userId);
        $marginExchangeUrl = margin_exchange_url($this->symbol);

        return __('emails.received_verify_document.dear_account', [], $locale)
            . " " . $email
            . "\n" . __('emails.margin_deleverage.body1', [], $locale)
            . "\n" . __('emails.margin_deleverage.body2', [], $locale)
            . "\n" . $marginExchangeUrl;
    }
}
