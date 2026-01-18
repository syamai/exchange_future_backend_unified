<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class BetaTesterActiveNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    private $status;
    private $pair;

    public function __construct($status, $pair = null)
    {
        $this->status = $status;
        $this->pair = $pair;
    }

    public function toMail($notifiable)
    {
        return new \App\Mail\BetaTesterActiveMail($notifiable, $this->pair);
    }
}
