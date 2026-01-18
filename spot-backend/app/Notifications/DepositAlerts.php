<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Session;
use App\Consts;
use App\Utils\BigNumber;
use Carbon\Carbon;

class DepositAlerts extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $currency;
    protected $transaction;

    public function __construct($transaction, $currency)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->currency = $currency;
        $this->transaction = $transaction;
    }

    public function toMail($notifiable)
    {
        $jsonData = null;
        try {
            $jsonData = json_decode($this->transaction);
        } catch (\Exception $e) {
            $jsonData = $this->transaction;
        }
        return new \App\Mail\DepositAlerts($jsonData, $this->currency);
    }

    public function toLine($notifiable)
    {
        return $this->getMessage($notifiable);
    }

    public function toTelegram($notifiable)
    {
        return $this->getMessage($notifiable);
    }

    private function getMessage($notifiable)
    {
        $total = BigNumber::new(abs($this->transaction->amount))->add($this->transaction->fee)->toString();
        $locale = Session::get('user.locale', Consts::DEFAULT_USER_LOCALE);
        return __('emails.deposit_alerts.line_2', [], $locale)
            . "\n" . __('emails.withdraw_alerts.line_3', [], $locale)
            . ": " . strtoupper($this->transaction->currency)
            . "\n" . __('emails.withdraw_alerts.line_4', [], $locale)
            . ": " .  $total
            . "\n" . __('emails.withdraw_alerts.line_5', [], $locale)
            . ": " .  $this->transaction->to_address
            . "\n" . __('emails.withdraw_alerts.line_6', [], $locale)
            . ": " .  (string)Carbon::now('UTC')
            . "\n" . __('emails.withdraw_alerts.line_8', [], $locale);
    }
}
