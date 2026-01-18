<?php

namespace App\Notifications;

use App\Http\Services\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class WithdrawalCanceledNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    private $transaction;
    private $userService;

    public function __construct($transaction)
    {
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
        return new \App\Mail\WithdrawalCanceledMail($notifiable, $jsonData);
    }
}
