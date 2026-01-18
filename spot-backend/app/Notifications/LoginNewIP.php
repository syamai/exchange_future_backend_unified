<?php

namespace App\Notifications;

use App\Consts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class LoginNewIP extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $device;

    public function __construct($device)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->device = $device;
    }

    public function toMail($notifiable)
    {
        return new \App\Mail\LoginNewIP($this->device);
    }
}
