<?php

namespace App\Notifications;

use App\Consts;
use App\Mail\RegisterDeniedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class RegisterDeniedNotification extends BaseNotification implements ShouldQueue
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
        return new RegisterDeniedMail($this->user);
    }
}
