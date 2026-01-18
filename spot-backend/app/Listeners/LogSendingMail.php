<?php

namespace App\Listeners;

class LogSendingMail
{
    public function __construct()
    {
        //
    }

    public function handle($event)
    {
        $response = $event->response;
        logger()->info("sending mail ======= " . $response);
        $notification = $event->notification;
        logger()->error("sending mail data  $notification ======" . $response->getMessage());
    }
}
