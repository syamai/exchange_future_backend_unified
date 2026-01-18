<?php

namespace App\Notifications;

use App\Consts;
use App\Http\Services\UserService;
use App\Mail\DepositUsdAlertsToAdmin as DepositUsdAlertsToAdminMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class DepositUsdAlertsToAdmin extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public $transaction;
    public $email;
    protected UserService $userService;

    public function __construct($email, $transaction)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->transaction = $transaction;
        $this->email = $email;
    }

    public function toMail($notifiable): DepositUsdAlertsToAdminMail
    {
        $jsonData = null;
        try {
            $jsonData = json_decode($this->transaction);
        } catch (\Exception $e) {
            $jsonData = $this->transaction;
        }
        return new DepositUsdAlertsToAdminMail($this->email, $jsonData);
    }

    public function toTelegram($notifiable): string
    {
        return $this->getMessage($notifiable);
    }

    public function toLine($notifiable): string
    {
        return $this->getMessage($notifiable);
    }

    private function getMessage($notifiable): string
    {
        $this->userService = new UserService();
        $locale = $this->userService->getUserLocale($notifiable->id);
        $message = __('emails.new_deposit_title', [], $locale)
        . " \n " . __('emails.new_deposit_title1', [], $locale)
        . " \n " . __('emails.new_deposit_title2', [], $locale);

        return $message;
    }
}
