<?php

namespace App\Notifications;

use App\Consts;
use App\Http\Services\UserService;
use App\Mail\DepositUsdAlertsToAdmin;
use App\Mail\WithdrawUsdAlertsToAdmin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Bus\Queueable;
use App\Utils\BigNumber;

class AdminUsdAlerts extends BaseNotification implements ShouldQueue
{
    use Queueable;

    private $transaction;
    private $userService;

    public function __construct($transaction)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->transaction = $transaction;
        $this->userService = new UserService();
    }

    public function toMail($notifiable)
    {
        $email = DB::table('settings')->where('key', Consts::SETTING_CONTACT_EMAIL)->pluck('value');

        if ($this->isDepositTransactionType($this->transaction)) {
            return new DepositUsdAlertsToAdmin($email, $this->transaction);
        } elseif ($this->isWithdrawTransactionType($this->transaction)) {
            return new WithdrawUsdAlertsToAdmin($email, $this->transaction);
        }
    }

    private function isWithdrawTransactionType($transaction)
    {
        $amount = BigNumber::new($transaction->amount)->sub($transaction->fee)->toString();
        return !!(BigNumber::new($amount)->comp(0) < 0);
    }

    private function isDepositTransactionType($transaction)
    {
        return !$this->isWithdrawTransactionType($transaction);
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

        if ($this->isDepositTransactionType($this->transaction)) {
            return ' ``` Sotatek Exchange  ```'
                . " \n " . __('m_funds.deposit_usd.deposit_usd_tab', [], $locale);
        } elseif ($this->isWithdrawTransactionType($this->transaction)) {
            return ' ``` Sotatek Exchange  ```'
                . " \n " . __('m_funds.withdraw_usd.withdraw_usd_tab', [], $locale);
        }
    }
}
