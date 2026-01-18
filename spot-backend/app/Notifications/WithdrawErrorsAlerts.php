<?php

namespace App\Notifications;

use App\Consts;
use App\Mail\WithdrawErrorsAlerts as WithdrawErrorsAlertsMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Session;

class WithdrawErrorsAlerts extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $currency;
    protected $transaction;

    public function __construct($transaction, $currency)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->currency = $currency;
        $this->transaction = $transaction;
        $this->getMessage();
    }

    public function toMail($notifiable): WithdrawErrorsAlertsMail
    {
        $jsonData = null;
        try {
            $jsonData = json_decode($this->transaction);
        } catch (\Exception $e) {
            $jsonData = $this->transaction;
        }
        return new WithdrawErrorsAlertsMail($this->currency, $jsonData);
    }

    public function toLine($notifiable): string
    {
        return $this->getMessage();
    }

    public function toTelegram($notifiable): string
    {
        return $this->getMessage();
    }

    private function getMessage(): string
    {
        $locale = Session::get('user.locale', Consts::DEFAULT_USER_LOCALE);
        return __('address.errors.blockchain_address', [], $locale);
    }
}
