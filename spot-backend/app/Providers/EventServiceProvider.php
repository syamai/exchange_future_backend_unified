<?php

namespace App\Providers;

use App\Listeners\LogSendingMail;
use App\Listeners\LogSentMail;
use Bugger\Bugger;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Queue;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Illuminate\Notifications\Events\NotificationSent' => [
            'App\Listeners\SendAdminNotification',
        ],
        // 'Illuminate\Database\Events\TransactionBeginning' => [
        //     'App\Listeners\RenewMarginCalculator'
        // ],

        MessageSending::class => [
            //LogSendingMail::class,
        ],

        MessageSent::class => [
            //LogSentMail::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        Queue::failing(function (JobFailed $event) {
            $bugger = new Bugger();
            $bugger->send($event->exception);
        });
    }
}
