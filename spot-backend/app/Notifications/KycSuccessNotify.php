<?php

namespace App\Notifications;

use App\Mail\KycSuccess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Consts;

class KycSuccessNotify extends BaseNotification implements ShouldQueue
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
     * @return KycSuccess
	 */
    public function toMail($notifiable)
	{
        return new KycSuccess($notifiable->email);
    }
}
