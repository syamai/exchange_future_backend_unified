<?php

namespace App\Listeners;

class LogSentMail
{
    public function __construct()
    {
        //
    }

    public function handle($event)
    {
        $response = $event->response;
        logger()->info("send mail done  ======= " . $response);
        $notification = $event->notification;
        logger()->error("send mail done $notification ======" . $response->getMessage());
    }
}
