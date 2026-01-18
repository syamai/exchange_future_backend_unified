<?php

namespace App\Notifications;

use App\Consts;
use App\Mail\RegisterCompletedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class RegisterCompletedNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $user;

    public function __construct($user)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->user = $user;
    }

    public function toMail()
    {
        return new RegisterCompletedMail($this->user);
    }
}
