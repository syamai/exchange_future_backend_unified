<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Consts;

class ChangePassword extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public $locale;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \App\Mail\ChangePassword
	 */
    public function toMail($notifiable)
	{
        return new \App\Mail\ChangePassword($notifiable->email);
    }
}
