<?php

namespace App\Notifications;

use App\Consts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Session;

class WithdrawUsdAlertsToAdmin extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected string $email;
    protected $transaction;
    private $message;

    public function __construct(string $email, $transaction)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->email = $email;
        $this->transaction = $transaction;
        $this->getMessage();
    }

    public function toMail($notifiable)
    {
        $jsonData = null;
        try {
            $jsonData = json_decode($this->transaction);
        } catch (\Exception $e) {
            $jsonData = $this->transaction;
        }
        return new \App\Mail\WithdrawUsdAlertsToAdmin($this->email, $jsonData);
    }

    public function toLine($notifiable)
    {
        return $this->message;
    }

    public function toTelegram($notifiable)
    {
        return $this->message;
    }

    private function getMessage()
    {
        $locale = Session::get('user.locale', Consts::DEFAULT_USER_LOCALE);
        $this->message = __('emails.withdraw_usd_alerts.title', [], $locale);
    }
}
