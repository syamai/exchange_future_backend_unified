<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Session;
use App\Consts;
use App\Utils\BigNumber;
use Carbon\Carbon;

class WithdrawalVerifyAlert extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $transaction;
    public $message;
    public $locale;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($transaction)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
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
        return new \App\Mail\WithdrawalVerifyAlert($jsonData);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toLine($notifiable)
    {
        return $this->getMessage($notifiable);
    }

    public function toTelegram($notifiable)
    {
        logger()->info('toTelegram: '.$notifiable->name);
        return $this->getMessage($notifiable);
    }

    private function getMessage($notifiable)
    {
        $locale = Session::get('user.locale', Consts::DEFAULT_USER_LOCALE);
        $this->message = "\n" . __('emails.withdraw_verify.line_2', [], $locale)
            . "\n" . __('emails.withdraw_verify.line_3', [], $locale)
            . ": " .  strtoupper($this->transaction->currency)
            . "\n" . __('emails.withdraw_verify.line_4', [], $locale)
            . ": " .  BigNumber::new(abs($this->transaction->amount))->toString()
            . "\n" . __('emails.withdraw_verify.line_5', [], $locale)
            . ": " .  $this->transaction->to_address
            . "\n" . __('emails.withdraw_verify.line_6', [], $locale)
            . ": " .  (string)Carbon::now('UTC')
            . "\n" . __('emails.withdraw_verify.line_7', [], $locale)
            . "\n" . withdrawal_verify_url($this->transaction->currency, $this->transaction->transaction_id)
            . "\n" . __('emails.withdraw_verify.line_8', [], $locale);
        return $this->message;
    }
}
