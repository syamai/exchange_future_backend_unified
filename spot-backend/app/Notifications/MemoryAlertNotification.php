<?php

namespace App\Notifications;

use App\Consts;
use App\Mail\MemoryAlertMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Log;

class MemoryAlertNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    private $data;

    public function __construct($data)
    {
        $this->queue = Consts::QUEUE_HANDLE_MAIL;
        $this->data = $data;
    }

    public function toMail($notifiable) 
    {
        return new MemoryAlertMail($notifiable, $this->data);
    }
}
