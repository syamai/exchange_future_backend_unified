<?php

namespace App\Notifications;

use App\Mail\KycFail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Consts;

class KycFailNotify extends BaseNotification implements ShouldQueue
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
     * @return KycFail
	 */
    public function toMail($notifiable)
	{
        return new KycFail($notifiable->email);
    }
}
