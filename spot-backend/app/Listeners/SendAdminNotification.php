<?php

namespace App\Listeners;

use App\Events\AdminNotificationUpdated;
use App\Jobs\ProcessSendAdminNotification;
use App\Notifications\TransactionCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendAdminNotification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        if ($event->notification instanceof TransactionCreated) {
            ProcessSendAdminNotification::dispatchIfNeed();
        }
    }
}
