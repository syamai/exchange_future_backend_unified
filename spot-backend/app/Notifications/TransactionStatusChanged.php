<?php

namespace App\Notifications;

use App\Consts;
use App\Utils\BigNumber;
use App\Http\Services\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Carbon\Carbon;

class TransactionStatusChanged extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $email;
    protected $transaction;
    private $userService;

    public function __construct($email, $transaction)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->email = $email;
        $this->transaction = $transaction;
        $this->userService = new UserService();
    }

    public function toMail($notifiable)
    {
        $jsonData = null;
        try {
            $jsonData = json_decode($this->transaction);
        } catch (\Exception $e) {
            $jsonData = $this->transaction;
        }
        return new \App\Mail\TransactionStatusChanged($this->email, $jsonData);
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
        $locale = $this->userService->getUserLocale($notifiable->id);
        $type = BigNumber::new($this->transaction->amount)->comp(0) > 0 ? 'deposit' : 'withdrawal';
        $result = $this->transaction->status == Consts::TRANSACTION_STATUS_SUCCESS ? 'approved' : 'rejected';
        return $this->CategoryMessage($type, $result, $locale);
    }

    private function getHiddenAccountNumber($accountNo)
    {
        if (!$accountNo) {
            return $accountNo;
        }

        $length = strlen($accountNo);
        if ($length > 4) {
            $accountNo = substr($accountNo, 0, 2) . str_repeat('*', $length - 4)
                . substr($accountNo, $length - 2);
        }
        return $accountNo;
    }

    private function CategoryMessage($type, $result, $locale)
    {
        if ($type == 'deposit') {
            if ($result == 'approved') {
                $message = "\n" . __('notifications.deposit_withdraw_usd_alerts.hello', [], $locale)
                . $this->email
                . "\n" . __('emails.deposit_withdraw_usd_alerts.deposit.approved.line_1')
                . "\n" . __('emails.deposit_withdraw_usd_alerts.deposit.approved.line_2')
                . "\n" . __('emails.deposit_withdraw_usd_alerts.deposit.approved.amount')
                . ": " . number_format(abs($this->transaction->amount))
                . "\n" . __('emails.deposit_withdraw_usd_alerts.time')
                . ": " . (string)Carbon::now('UTC') . " (UTC)" ;
                return $message;
            } else {
                $message = "\n" . __('notifications.deposit_withdraw_usd_alerts.hello', [], $locale)
                . $this->email
                . "\n" . __('emails.deposit_withdraw_usd_alerts.deposit.rejected.line_1')
                . "\n" . __('emails.deposit_withdraw_usd_alerts.deposit.rejected.line_2')
                . "\n" . __('emails.deposit_withdraw_usd_alerts.deposit.approved.amount')
                . ": " . number_format(abs($this->transaction->amount))
                . "\n" . __('emails.deposit_withdraw_usd_alerts.time')
                . ": " . (string)Carbon::now('UTC') . " (UTC)";
                return $message;
            }
        } else {
            if ($result == 'approved') {
                $message = "\n" . __('notifications.deposit_withdraw_usd_alerts.hello', [], $locale)
                . $this->email
                . "\n" . __('emails.deposit_withdraw_usd_alerts.withdrawal.approved.line_1')
                . "\n" . __('emails.deposit_withdraw_usd_alerts.withdrawal.approved.amount')
                . ": " . number_format(abs($this->transaction->amount))
                . "\n" . __('emails.deposit_withdraw_usd_alerts.withdrawal.approved.account_bank')
                . ": " . $this->transaction->bank_name
                . "\n" . __('emails.deposit_withdraw_usd_alerts.withdrawal.approved.account_number')
                . ": " . $this->getHiddenAccountNumber($this->transaction->account_no)
                . "\n" . __('emails.deposit_withdraw_usd_alerts.withdrawal.approved.account_holder')
                . ": " . $this->transaction->account_name
                . "\n" . __('emails.deposit_withdraw_usd_alerts.time')
                . ": " . (string)Carbon::now('UTC') . " (UTC)";
                return $message;
            } else {
                $message = "\n" . __('notifications.deposit_withdraw_usd_alerts.hello', [], $locale)
                . $this->email
                . "\n" . __('emails.deposit_withdraw_usd_alerts.withdrawal.rejected.line_1')
                . "\n" . __('emails.deposit_withdraw_usd_alerts.withdrawal.rejected.line_2')
                . "\n" . __('emails.deposit_withdraw_usd_alerts.withdrawal.rejected.amount')
                . ": " . number_format(abs($this->transaction->amount))
                . "\n" . __('emails.deposit_withdraw_usd_alerts.withdrawal.rejected.account_bank')
                . ": " . $this->transaction->bank_name
                . "\n" . __('emails.deposit_withdraw_usd_alerts.withdrawal.rejected.account_number')
                . ": " . $this->getHiddenAccountNumber($this->transaction->account_no)
                . "\n" . __('emails.deposit_withdraw_usd_alerts.withdrawal.rejected.account_holder')
                . ": " . $this->transaction->account_name
                . "\n" . __('emails.deposit_withdraw_usd_alerts.time')
                . ": " . (string)Carbon::now('UTC') . " (UTC)";
                return $message;
            }
        }
    }
}
