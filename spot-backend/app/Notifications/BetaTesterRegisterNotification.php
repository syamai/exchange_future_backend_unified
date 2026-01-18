<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class BetaTesterRegisterNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    private $status;

    public function __construct($status)
    {
        $this->status = $status;
    }

    public function toMail($notifiable)
    {
        return new \App\Mail\BetaTesterRegisterMail($notifiable);
    }
}
